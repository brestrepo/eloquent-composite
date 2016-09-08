<?php
namespace Composite\Eloquent\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

class BelongsToComposite extends Relation
{

    /**
     * The foreign keys of the parent model.
     *
     * @var array
     */
    protected $foreignKey;

    /**
     * The associated keys on the parent model.
     *
     * @var array
     */
    protected $otherKey;

    /**
     * The name of the relationship.
     *
     * @var string
     */
    protected $relation;

    /**
     * Create a new belongs to relationship instance.
     *
     * BelongsToComposite constructor.
     * @param Builder $query
     * @param Model $parent
     * @param $foreignKey
     * @param $otherKey
     * @param $relation
     */
    public function __construct(Builder $query, Model $parent, $foreignKey, $otherKey, $relation)
    {
        $this->otherKey = $otherKey;
        $this->relation = $relation;
        $this->foreignKey = $foreignKey;

        parent::__construct($query, $parent);
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->query->first();
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @throws \Exception
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $table = $this->related->getTable();

            if (is_array($this->foreignKey)) {
                foreach ($this->foreignKey as $key => $value) {
                    if (isset($this->otherKey[$key])) {
                        $currentKey = $this->parent->{$value};

                        $this->query->where($table.'.'.$this->otherKey[$key], '=', $currentKey);
                    } else {
                        throw new \Exception('Asymmetrical foreign key and local key');
                    }
                }
            } else {
                // For belongs to relationships, which are essentially the inverse of has one
                // or has many relationships, we need to actually query on the primary key
                // of the related models matching on the foreign key that's on a parent.


                $this->query->where($table.'.'.$this->otherKey, '=', $this->parent->{$this->foreignKey});
            }
        }
    }

    /**
     * Add the constraints for a relationship count query.
     *
     * @param Builder $query
     * @param Builder $parent
     * @return Builder
     * @throws \Exception
     */
    public function getRelationCountQuery(Builder $query, Builder $parent)
    {
        $query->select(new Expression('count(*)'));

        $otherKeys = $this->wrapKeys($query);
        $qualifiedForeignKeys = $this->getQualifiedForeignKey();

        if (count($otherKeys) != count($qualifiedForeignKeys)) {
            throw new \Exception('Asymmetrical foreign key and parent key');
        }

        foreach ($otherKeys as $key => $wrappedKey) {
            $query->where($qualifiedForeignKeys[$key], '=', new Expression($wrappedKey));
        }

        return $query;
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        // We'll grab the primary key name of the related models since it could be set to
        // a non-standard name and not "id". We will then construct the constraint for
        // our eagerly loading query so it returns the proper models from execution.

        $keys = $this->getRelatedTableKeys();

        foreach ($keys as $key => $keyName) {
            $this->query->whereIn($keyName, $this->getEagerModelKeys($models, $this->foreignKey[$key]));
        }
    }

    /**
     * Returns a composed list of keys with the related table name
     *
     * @return array
     */
    public function getRelatedTableKeys()
    {
        $keys = [];

        foreach ($this->otherKey as $keyName) {
            $keys[] = $this->related->getTable() . '.' . $keyName;
        }

        return $keys;
    }

    /**
     * Gather the keys from an array of related models.
     *
     * @param  array  $models
     * @return array
     */
    protected function getEagerModelKeys(array $models, $key)
    {
        $keys = array();

        // First we need to gather all of the keys from the parent models so we know what
        // to query for via the eager loading query. We will add them to an array then
        // execute a "where in" statement to gather up all of those related records.
        foreach ($models as $model) {
            if (! is_null($value = $model->{$key})) {
                $keys[] = $value;
            }
        }

        // If there are no keys that were not null we will just return an array with 0 in
        // it so the query doesn't fail, but will not return any results, which should
        // be what this developer is expecting in a case where this happens to them.
        if (count($keys) == 0) {
            return array(0);
        }

        return array_values(array_unique($keys));
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array   $models
     * @param  string  $relation
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array   $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        $foreign = $this->foreignKey;

        $other = $this->otherKey;

        // First we will get to build a dictionary of the child models by their primary
        // key of the relationship, then we can easily match the children back onto
        // the parents using that dictionary and the primary key of the children.
        $dictionary = array();

        foreach ($results as $result) {
            $key = $this->makeAttributesKey($other, $result);

            $dictionary[$key] = $result;
        }

        // Once we have the dictionary constructed, we can loop through all the parents
        // and match back onto their children using these keys of the dictionary and
        // the primary key of the children to map them onto the correct instances.
        foreach ($models as $model) {
            $key = $this->makeCompositeKeyValues($foreign, $model);

            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }

    /**
     * Return a composed key made out of arguments
     *
     * @param $keys
     * @param $result
     * @return mixed
     */
    public function makeAttributesKey($keys, $result)
    {
        $key = [];
        foreach ($keys as $keyName) {
            $key[] = $result->getAttribute($keyName);
        }

        return implode('+', $key);
    }

    /**
     * @param $keys
     * @return mixed
     */
    public function makeCompositeKey($keys)
    {
        $key = [];

        foreach ($keys as $keyName) {
            $key[] = $keyName;
        }

        return implode('+', $key);
    }

    /**
     * @param $keys
     * @param $model
     * @return mixed
     */
    public function makeCompositeKeyValues($keys, $model)
    {
        $key = [];

        foreach ($keys as $keyName) {
            $key[] = $model->{$keyName};
        }

        return implode('+', $key);
    }

    /**
     * Associate the model instance to the given parent.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function associate(Model $model)
    {

        $this->parent->setAttribute(
            $this->makeCompositeKey($this->foreignKey),
            $this->makeAttributesKey($this->otherKey, $model)
        );

        return $this->parent->setRelation($this->relation, $model);
    }



    /**
     * Dissociate previously associated model from the given parent.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function dissociate()
    {
        $this->parent->setAttribute($this->makeCompositeKey($this->foreignKey), null);

        return $this->parent->setRelation($this->relation, null);
    }

    /**
     * Update the parent model on the relationship.
     *
     * @param  array  $attributes
     * @return mixed
     */
    public function update(array $attributes)
    {
        $instance = $this->getResults();

        return $instance->fill($attributes)->save();
    }

    /**
     * Get the foreign key of the relationship.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return $this->makeCompositeKey($this->foreignKey);
    }

    /**
     * Get the fully qualified foreign keys of the relationship.
     *
     * @return string
     */
    public function getQualifiedForeignKey()
    {
        if (is_array($this->foreignKey)) {
            $qualifiedForeignKeys = [];
            $table = $this->parent->getTable();

            foreach ($this->foreignKey as $keyName) {
                $qualifiedForeignKeys[] = $table . '.' . $keyName;
            }

            return $qualifiedForeignKeys;
        } else {
            return $this->parent->getTable() . '.' . $this->foreignKey;
        }
    }

    /**
     * Get the associated key of the relationship.
     *
     * @return string
     */
    public function getOtherKey()
    {
        return $this->makeCompositeKey($this->otherKey);
    }

    /**
     * Get the fully qualified associated key of the relationship.
     *
     * @return string
     */
    public function getQualifiedOtherKeyName()
    {
        if (is_array($this->foreignKey)) {
            $qualifiedOtherKeys = [];
            $table = $this->related->getTable();

            foreach ($this->otherKey as $keyName) {
                $qualifiedOtherKeys[] = $table . '.' . $keyName;
            }

            return $qualifiedOtherKeys;
        } else {
            return $this->related->getTable() . '.' . $this->otherKey;
        }
    }

    /**
     * Return the parent table keys wrapped
     *
     * @param $query
     * @return array
     */
    public function wrapKeys(&$query)
    {
        $otherKeys = [];

        foreach ($this->otherKey as $keyName) {
            $this->wrap($query->getModel()->getTable().'.'.$keyName);
        }

        return $otherKeys;
    }
}
