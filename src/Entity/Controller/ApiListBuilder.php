<?php

namespace Drupal\api_manager\Entity\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for api_manager entity.
 *
 * @ingroup api_manager
 */
class ApiListBuilder extends EntityListBuilder {

  /**
   * The url generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
      $container->get('url_generator')
    );
  }

  /**
   * Constructs a new ApiListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, UrlGeneratorInterface $url_generator) {
    parent::__construct($entity_type, $storage);
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   *
   * We override ::render() so that we can add our own content above the table.
   * parent::render() is where EntityListBuilder creates the table using our
   * buildHeader() and buildRow() implementations.
   */
  public function render() {
    $build['description'] = [
      '#markup' => $this->t('The sync require cron to be functional OR you can disable importing on cron in the "interval" field to make use of external triggers', [
        '@adminlink' => $this->urlGenerator->generateFromRoute('api_manager.api_settings'),
      ]),
    ];
    $build['table'] = parent::render();
    return $build;
  }

  /**
   * Gets this list's default operations.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the operations are for.
   *
   * @return array
   *   The array structure is identical to the return value of
   *   self::getOperations().
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    if ($entity->access('import') && $entity->hasLinkTemplate('import-form')) {
      $operations['import'] = array(
        'title' => $this->t('Run import'),
        'weight' => 30,
        'url' => $this->ensureDestination($entity->toUrl('import-form')),
      );
    }

    if ($entity->access('view') && $entity->hasLinkTemplate('bulkdelete-form')) {
      $operations['bulkdelete'] = array(
        'title' => $this->t('Delete imported'),
        'weight' => 31,
        'url' => $this->ensureDestination($entity->toUrl('bulkdelete-form')),
      );
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   *
   * Building the header and content lines for the api list.
   *
   * Calling the parent::buildHeader() adds a column for the possible actions
   * and inserts the 'edit' and 'delete' links as defined for the entity type.
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['api_destination_entity'] = $this->t('Destination entity');
    $header['weight'] = $this->t('Weight');
    $header['active'] = $this->t('Active');
    $header['items_in_sync'] = $this->t('Total items in sync');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\api_manager\Entity\Api */
    ApiManagerSetResult($entity->id(), $entity->label() . '_sync');

    $api_sync_field = $entity->get('api_sync_field');
    $langcode = $entity->get('api_destination_language');
    $bundle = $entity->get('api_destination_entity');

    $type = ApiManagerDetermineEntityType($bundle);
    switch($type) {
      case 'node':
        $ids = \Drupal::entityQuery('node')
          ->condition('langcode', $langcode)
          ->condition($api_sync_field, '', '<>')
          ->condition('type', $bundle)
          ->execute();
        break;
      case 'taxonomy':
        $ids = \Drupal::entityQuery('taxonomy_term')
          ->condition($api_sync_field, '', '<>')
          ->condition('vid', $bundle, '=')
          ->execute();
        break;
    }

    $row['name'] = $entity->label();
    $row['api_destination_entity'] = $entity->get('api_destination_entity');
    $row['weight'] = $entity->get('api_manager_weight');
    $row['active'] = ($entity->get('api_manager_active') === true) ? 'Yes' : 'No';
    $row['items_in_sync'] = count($ids);

    $status = \Drupal::state()->get('api_manager_status_'.$entity->id(), 'ok');
    if($status === 'ok') {
      $statusHtml = '<img src="/core/themes/stable/images/core/icons/73b355/check.svg" />';
    }
    if($status === 'error') {
      $statusHtml = '<img src="/core/themes/stable/images/core/icons/e32700/error.svg" />';
    }
    $statusMessage = \Drupal::state()->get('api_manager_'.$entity->id(), 'Ready to start importing');
    $row['status']['data']['#markup'] = $statusHtml . '<br>' . $statusMessage;
    return $row + parent::buildRow($entity);
  }



}
