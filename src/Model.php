<?php
namespace Composite\Eloquent;

use Composite\Eloquent\Relations\HasManyComposite;
use Illuminate\Database\Eloquent\Relations\Relation;
use LogicException;

/**
 * Class Model
 * @package Composite\Eloquent
 */
class Model extends \Illuminate\Database\Eloquent\Model
{
    /**
     * @var
     */
    protected $compositeKey;

    /**
     * Define a one-to-many relationship that uses composite keys.
     *
     * @param $related
     * @param array $foreignKey
     * @param array $localKey
     * @return HasManyComposite
     */
    public function hasManyComposite($related, array $foreignKey, array $localKey)
    {
        $instance = new $related;

        return new HasManyComposite($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * Get a relationship value from a method.
     *
     * @param string $key
     * @param string $camelKey
     * @return mixed
     * @throws LogicException
     */
    protected function getRelationshipFromMethod($key, $camelKey)
    {
        $relations = $this->$camelKey();

        if (!$relations instanceof Relation) {
            throw new LogicException('Relationship method must return an object of type '
                . 'Illuminate\Database\Eloquent\Relations\Relation');
        }

        if (!is_array($key)) {
            return $this->relations[$key] = $relations->getResults();
        } else {
            return $this->relations[$camelKey] = $relations->getResults();
        }
    }
}
