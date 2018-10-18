<?php

namespace Drupal\Core\Database\Driver\primary_primary;

use Drupal\Core\Database\Driver\mysql\Insert as MysqlInsert;

class Insert extends MysqlInsert {
  public function __toString() {
    // Create a sanitized comment string to prepend to the query.
    $comments = $this->connection->makeComment($this->comments);

    // Default fields are always placed first for consistency.
    $insert_fields = array_merge($this->defaultFields, $this->insertFields);

    // If we're selecting from a SelectQuery, finish building the query and
    // pass it back, as any remaining options are irrelevant.
    if (!empty($this->fromQuery)) {
      $insert_fields_string = $insert_fields ? ' (' . implode(', ', $insert_fields) . ') ' : ' ';
      return $comments . 'REPLACE INTO {' . $this->table . '}' . $insert_fields_string . $this->fromQuery;
    }

    $query = $comments . 'REPLACE INTO {' . $this->table . '} (' . implode(', ', $insert_fields) . ') VALUES ';

    $values = $this->getInsertPlaceholderFragment($this->insertValues, $this->defaultFields);
    $query .= implode(', ', $values);

    return $query;
  }

}

