<?php

declare(strict_types=1);

namespace Light\Model\Driver\Mongodb;

use Exception;
use IteratorIterator;
use Light\Model\Exception\CallUndefinedMethod;
use Light\Model\Exception\ConfigWasNotProvided;
use Light\Model\Exception\DriverClassDoesNotExists;
use Light\Model\Exception\DriverClassDoesNotExtendsFromDriverAbstract;
use Light\Model\Model;
use Light\Model\Driver\CursorAbstract;
use Light\Model\Driver\Exception\IndexOutOfRange;
use MongoDB\BSON\ObjectID;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;

/**
 * Class Cursor
 * @package Light\Model\Driver\Mongodb
 */
class Cursor extends CursorAbstract
{
  /**
   * @var \MongoDB\Driver\Cursor
   */
  private $_cursor = null;

  /**
   * @var IteratorIterator
   */
  private $_iterator = null;

  /**
   * @var int
   */
  private $_count = -1;

  /**
   * @var Query
   */
  private $_query = null;

  /**
   * Cursor constructor.
   *
   * @param Model $model
   * @param Query $query
   * @param array $config
   */
  public function __construct(Model $model, Query $query, array $config = [])
  {
    parent::__construct($model, [], $config);
    $this->_query = $query;
  }

  /**
   * @param int $offset
   * @return bool
   */
  public function offsetExists($offset): bool
  {
    if (is_numeric($offset)) {

      $this->rewind();

      for ($i = 0; $i < $offset; $i++) {
        $this->next();
      }

      return $this->valid();
    }

    return false;
  }

  /*************** \ArrayIterator implementation ***********/

  /**
   *
   */
  public function rewind(): void
  {
    $this->_cursor = $this->_executeQuery($this->_query);
    $this->_iterator = new IteratorIterator($this->_cursor);

    $this->_iterator->rewind();
  }

  /**
   * @param Query $query
   * @return \MongoDB\Driver\Cursor
   */
  private function _executeQuery(Query $query): \MongoDB\Driver\Cursor
  {
    /** @var Manager $manager */
    $manager = $this->getModel()->getManager();

    return $manager->executeQuery(
      $this->getCollectionNamespace(),
      $query
    );
  }



  /*************** \Iterator implementation ***********/

  /**
   * @return string
   */
  public function getCollectionNamespace(): string
  {
    return implode('.', [
      $this->getConfig()['db'],
      $this->getModel()->getMeta()->getCollection()
    ]);
  }

  /**
   * return null
   */
  public function next(): void
  {
    $this->_iterator->next();
  }

  /**
   * @return bool
   */
  public function valid(): bool
  {
    return $this->_iterator->valid();
  }

  /**
   * @param $offset
   * @return mixed
   * @throws IndexOutOfRange
   * @throws CallUndefinedMethod
   * @throws ConfigWasNotProvided
   * @throws DriverClassDoesNotExists
   * @throws DriverClassDoesNotExtendsFromDriverAbstract
   */
  public function offsetGet($offset): mixed
  {
    if (isset($this->_documents[$offset])) {
      return $this->_documents[$offset];
    }

    $this->rewind();

    for ($i = 0; $i < $offset; $i++) {

      try {
        $this->_iterator->next();
      } catch (Exception $e) {
        throw new IndexOutOfRange($offset, $i + 1);
      }
    }

    $this->_documents[$offset] = $this->_getDataModel();
    return $this->_documents[$offset];
  }

  /**
   * @return Model
   * @throws CallUndefinedMethod
   * @throws ConfigWasNotProvided
   * @throws DriverClassDoesNotExists
   * @throws DriverClassDoesNotExtendsFromDriverAbstract
   */
  private function _getDataModel(): Model
  {
    $currentItem = $this->_iterator->current();

    if ($currentItem->_id instanceof ObjectID) {
      $currentItem->_id = (string)$currentItem->_id;
    }

    $data = json_decode(json_encode($currentItem), true);
    $modelClassName = $this->getModel()->getModelClassName();

    /** @var Model $model */
    $model = new $modelClassName();
    $model->populateWithoutQuerying($this->processDataRow($data));

    return $model;
  }


  /*************** \Countable implementation ***********/

  /**
   * @param array $data
   * @return array
   */
  public function processDataRow(array $data): array
  {
    if (isset($data['_id'])) {
      $data['id'] = $data['_id'];
      unset($data['_id']);
    }

    return $data;
  }

  /**
   * @return Model|mixed
   * @throws CallUndefinedMethod
   * @throws ConfigWasNotProvided
   * @throws DriverClassDoesNotExists
   * @throws DriverClassDoesNotExtendsFromDriverAbstract
   */
  public function current(): mixed
  {
    $offset = $this->_iterator->key();

    if (isset($this->_documents[$offset])) {
      return $this->_documents[$offset];
    }

    $this->_documents[$offset] = $this->_getDataModel();
    return $this->_documents[$offset];
  }

  /**
   * @return mixed
   */
  public function key(): mixed
  {
    return $this->_iterator->key();
  }

  /**
   * @return int
   */
  public function count(): int
  {
    if ($this->_count == -1) {

      $queryResult = $this->_executeQuery($this->_query);
      $this->_count = count($queryResult->toArray());
    }

    return $this->_count;
  }
}
