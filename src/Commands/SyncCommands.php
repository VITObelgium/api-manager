<?php

namespace  Drupal\api_manager\Commands;

use Drush\Commands\DrushCommands;
use Drupal\api_manager\apiManager;


/**
 * Class ImportCommands
 * @package Drupal\api_manager\Commands
 */
class SyncCommands extends DrushCommands {

  /**
 * Runs the import command.
 *
 * @param string $name
 *   Argument provided to the drush command.
 *
 * @command import
 * @usage drush import legislation
 *   Display '20 items imported'.
 */
  public function import($name) {
    $result = mapExternalContent::run($name);
    drush_print($result. ' nodes '.$name.' imported.');
  }

  /**
   * Runs the delete command.
   *
   * @param string $name
   *   Argument provided to the drush command.
   *
   * @command delete
   * @usage drush delete job
   *   Display '20 items deleted'.
   */
  public function delete($name) {
    $result = deleteContent::run($name);
    drush_print($result. ' nodes '.$name.' deleted.');
  }

  /**
   * Runs the menu ops command.
   *
   * @param string $name
   *   Argument provided to the drush command.
   *
   * @command menuops
   * @usage drush menuops bbt
   *   Display 'bbt items migrated'.
   */
  public function menuops($name) {
    $result = menuOps::run($name);
    drush_print($result. ' menu items updated.');
  }


  /**
   * Runs the menu ops command.
   *
   * @param string $name
   *   Argument provided to the drush command.
   *
   * @command menuopsdelete
   * @usage drush menuopsdelete bbt
   *   Display '99 items deleted'.
   */
  public function menuopsdelete($name) {
    $result = menuOps::delete($name);
    drush_print($result. ' menu items deleted.');
  }

  /**
   * Runs the postops command.
   *
   * @param string $name
   *   Argument provided to the drush command.
   *
   * @command postops
   * @usage drush postops bbt
   *   Display '99 items updated'.
   */
  public function postops($name) {
    $result = postOps::run($name);
    drush_print($result);
  }

}
