<?php

namespace Drupal\api_manager\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\api_manager\ApiInterface;
/**
 * Defines the ContentEntityExample entity.
 *
 * @ingroup api_manager
 *
 * @ConfigEntityType(
 *   id = "api_manager",
 *   label = @Translation("Api entity"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\api_manager\Entity\Controller\ApiListBuilder",
 *     "form" = {
 *       "default" = "Drupal\api_manager\Form\ApiForm",
 *       "delete" = "Drupal\api_manager\Form\ApiDeleteForm",
 *       "import" = "Drupal\api_manager\Form\ApiImportForm",
 *       "bulkdelete" = "Drupal\api_manager\Form\ApiBulkdeleteForm",
 *     },
 *     "access" = "Drupal\api_manager\ApiAccessControlHandler",
 *   },
 *   config_prefix = "api_manager",
 *   list_cache_contexts = { "user" },
 *   admin_permission = "administer api entity",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "api_url" = "api_url",
 *     "api_user_id" = "user_id",
 *     "api_destination_entity" = "destination_entity",
 *     "api_sync_field" = "api_sync_field",
 *     "api_unique_id" = "api_unique_id",
 *     "api_updated_identifier" = "api_updated_identifier",
 *     "api_taxonomy_parent" = "api_taxonomy_parent",
 *     "api_type" = "api_type",
 *     "api_destination_language" = "api_destination_language",
 *     "api_minutes" = "api_minutes",
 *     "api_manager_textfield" = "api_minutes_textfield",
 *     "api_manager_textarea" = "api_manager_textarea",
 *     "api_manager_image" = "api_manager_image",
 *     "api_manager_image_root" = "api_manager_image_root",
 *     "api_manager_entity" = "api_manager_entity",
 *     "api_manager_integer" = "api_manager_integer",
 *     "api_manager_date" = "api_manager_date",
 *     "api_manager_list" = "api_manager_list",
 *     "api_manager_geolocation" = "api_manager_geolocation",
 *     "api_manager_weight" = "api_manager_weight",
 *     "api_manager_active" = "api_manager_active"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "api_url",
 *     "api_user_id",
 *     "api_destination_entity",
 *     "api_sync_field",
 *     "api_unique_id",
 *     "api_updated_identifier",
 *     "api_taxonomy_parent",
 *     "api_type",
 *     "api_destination_language",
 *     "api_minutes",
 *     "api_manager_textfield",
 *     "api_manager_textarea",
 *     "api_manager_image",
 *     "api_manager_image_root",
 *     "api_manager_entity",
 *     "api_manager_integer",
 *     "api_manager_date",
 *     "api_manager_geolocation",
 *     "api_manager_weight",
 *     "api_manager_list",
 *     "api_manager_active"
 *   },
 *   links = {
 *     "canonical" = "/api_manager/{api_manager}",
 *     "edit-form" = "/api_manager/{api_manager}/edit",
 *     "delete-form" = "/api/{api_manager}/delete",
 *     "import-form" = "/api/{api_manager}/import",
 *     "bulkdelete-form" = "/api/{api_manager}/bulkdelete",
 *     "collection" = "/api_manager/list"
 *   },
 * )
 *
 */

class Api extends ConfigEntityBase implements ApiInterface {

}
