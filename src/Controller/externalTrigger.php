<?php

namespace Drupal\api_manager\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\api_manager;
use Symfony\Component\HttpFoundation\JsonResponse;

class externalTrigger extends ControllerBase {

  public static function receive($uuid) {
    // Block non-valid uuids
    if(!Uuid::isValid($uuid)) {
      throw new AccessDeniedHttpException();
    }
    // Check if uuid is of type api
    $entity = \Drupal::service('entity.repository')->loadEntityByUuid('api_manager', $uuid);
    if (!$entity instanceof api_manager\ApiInterface) {
      throw new \exception('UUID ' . $uuid . ' is not a correct api call.');
    }

    $apiManager = new api_manager\ApiManager;
    $result = $apiManager->startJobs($entity);

    if($result === 1) {
      return new JsonResponse('success', 200, ['Content-Type'=> 'application/json']);
    } else {
      return new JsonResponse('failed', 200, ['Content-Type'=> 'application/json']);
    }

  }

}


