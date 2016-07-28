<?php
namespace Composite\Eloquent;

use Composite\Eloquent\Relations\HasManyComposite;
use Composite\Eloquent\Relations\BelongsToComposite;
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
     * @param array $otherKey
     * @return HasManyComposite
     */
    public function hasManyComposite($related, array $foreignKey, array $otherKey)
    {
        $instance = new $related;

        return new HasManyComposite($instance->newQuery(), $this, $foreignKey, $otherKey);
    }

    /**
     * Define an inverse one-to-one or many relationship that uses composite keys.
     *
     * @param  string  $related
     * @param  array  $foreignKey
     * @param  array  $otherKey
     * @param  string  $relation
     * @return BelongsToComposite
     */
    public function belongsToComposite($related, array $foreignKey, array $otherKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation)) {
            list(, $caller) = debug_backtrace(false, 2);

            $relation = $caller['function'];
        }

        $instance = new $related;

        // Once we have the foreign key names, we'll just create a new Eloquent query
        // for the related models and returns the relationship instance which will
        // actually be responsible for retrieving and hydrating every relations.
        $query = $instance->newQuery();

        return new BelongsToComposite($query, $this, $foreignKey, $otherKey, $relation);
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
