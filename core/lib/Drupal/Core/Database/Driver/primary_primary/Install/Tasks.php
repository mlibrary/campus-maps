<?php

namespace Drupal\Core\Database\Driver\primary_primary\Install;

use Drupal\Core\Database\Driver\mysql\Install\Tasks as MysqlTasks;

class Tasks extends MysqlTasks {

  const MYSQL_MINIMUM_VERSION = '5.6';
  const MARIADB_MINIMUM_VERSION = '10.0';

}

