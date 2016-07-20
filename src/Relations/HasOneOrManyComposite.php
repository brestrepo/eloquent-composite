<?php namespace Composite\Eloquent\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Class HasOneOrManyComposite
 * @package Composite\Eloquent\Relations
 */
abstract class HasOneOrManyComposite extends Relation
{
    /**
     * The foreign key of the parent model.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * The local key of the parent model.
     *
     * @var string
     */
    protected $localKey;

    /**
     * Create a new has many relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return void
     */
    public function __construct(Builder $query, Model $parent, $foreignKey, $localKey)
    {
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;

        parent::__construct($query, $parent);
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @throws \Exception
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            if (is_array($this->foreignKey)) {
                foreach ($this->foreignKey as $key => $value) {
                    if (isset($this->localKey[$key])) {
                        $this->query->where($value, '=', $this->parent->getAttribute($this->localKey[$key]));
                    } else {
                        throw new \Exception('Asymmetrical foreign key and local key');
                    }
                }
            } else {
                $this->query->where($this->foreignKey, '=', $this->getParentKey());
            }
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @throws \Exception
     */
    public function addEagerConstraints(array $models)
    {
        if (is_array($this->foreignKey)) {
            foreach ($this->foreignKey as $key => $value) {
                if (isset($this->localKey[$key])) {
                    $this->query->whereIn($value, $this->getKeys($models, $this->localKey[$key]));
                } else {
                    throw new \Exception('Asymmetrical foreign key and local key');
                }
            }
        } else {
            $this->query->whereIn($this->foreignKey, $this->getKeys($models, $this->localKey));
        }
    }

    /**
     * Match the eagerly loaded results to their single parents.
     *
     * @param  array   $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function matchOne(array $models, Collection $results, $relation)
    {
        return $this->matchOneOrMany($models, $results, $relation, 'one');
    }

    /**
     * Match the eagerly loaded results to their many parents.
     *
     * @param  array   $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function matchMany(array $models, Collection $results, $relation)
    {
        return $this->matchOneOrMany($models, $results, $relation, 'many');
    }

    /**
     * Match the eagerly loaded results to their many parents.
     *
     * @param  array   $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @param  string  $type
     * @return array
     */
    protected function matchOneOrMany(array $models, Collection $results, $relation, $type)
    {
        $dictionary = $this->buildDictionary($results);

        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        // Since we're dealing with a composite key, we need to create a composite key that matches with the dictionary
        foreach ($models as $model) {
            $keyArray = [];
            foreach ($this->localKey as $key => $value) {
                $keyArray[] = $model->getAttribute($value);
            }
            $key = implode('+', $keyArray);

            if (isset($dictionary[$key])) {
                $value = $this->getRelationValue($dictionary, $key, $type);

                $model->setRelation($relation, $value);
            }
        }

        return $models;
    }

    /**
     * Get the value of a relationship by one or many type.
     *
     * @param  array   $dictionary
     * @param  string  $key
     * @param  string  $type
     * @return mixed
     */
    protected function getRelationValue(array $dictionary, $key, $type)
    {
        $value = $dictionary[$key];

        return $type == 'one' ? reset($value) : $this->related->newCollection($value);
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        $dictionary = array();

        $foreign = $this->getPlainForeignKey();

        if (!is_array($foreign)) {
            $foreign = [$foreign];
        }

        // First we will create a dictionary of models keyed by the foreign key of the
        // relationship as this will allow us to quickly access all of the related
        // models without having to do nested looping which will be quite slow.
        // Since we're dealing with composite keys we need to create a composite key
        foreach ($results as $result) {
            $keyArray = [];
            foreach ($foreign as $key => $value) {
                $keyArray[] = $result->{$value};
            }
            $key = implode('+', $keyArray);
            $dictionary[$key][] = $result;
        }

        return $dictionary;
    }

    /**
     * TODO:
     *
     * @param Model $model
     * @throws \Exception
     */
    public function save(Model $model)
    {
        throw new \Exception('Method not implemented.');
    }

    /**
     * TODO:
     *
     * @param array $models
     * @throws \Exception
     */
    public function saveMany(array $models)
    {
        throw new \Exception('Method not implemented.');
    }

    /**
     * TODO:
     *
     * @param array $attributes
     * @throws \Exception
     */
    public function create(array $attributes)
    {
        throw new \Exception('Method not implemented.');
    }

    /**
     * TODO:
     *
     * @param array $records
     * @throws \Exception
     */
    public function createMany(array $records)
    {
        throw new \Exception('Method not implemented.');
    }

    /**
     * TODO:
     *
     * @param array $attributes
     * @throws \Exception
     */
    public function update(array $attributes)
    {
        throw new \Exception('Method not implemented.');
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getHasCompareKey()
    {
        return $this->getForeignKey();
    }

    /**
     * Get the foreign key for the relationship.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }

    /**
     * Get the plain foreign key.
     *
     * @return string
     */
    public function getPlainForeignKey()
    {
        if (!is_array($this->getForeignKey())) {
            $segments = explode('.', $this->getForeignKey());

            return $segments[count($segments) - 1];
        } else {
            return $this->getForeignKey();
        }
    }

    /**
     * Get the key value of the parent's local key.
     *
     * @return mixed
     */
    public function getParentKey()
    {
        return $this->parent->getAttribute($this->localKey);
    }

    /**
     * Get the fully qualified parent key name.
     *
     * @return string
     */
    public function getQualifiedParentKeyName()
    {
        return $this->parent->getTable().'.'.$this->localKey;
    }

}
