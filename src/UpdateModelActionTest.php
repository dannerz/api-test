<?php

namespace Dannerz\ApiTest;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Sota\ApiTest\Tests\ActionTest;

abstract class UpdateModelActionTest extends ActionTest
{
    /*
     * TODO: Apply 'surrounding asserts' to each test (ensure all surrounding data wasn't affected).
     * TODO: Load relations on return (add to service then to this test).
     * TODO: Immutable if relations testing.
     * TODO: Syncs arrays can be merged (hasMany & belongsToMany sync arrays to be merged).
     * TODO: Immutable tests not a fan of 'Something Obscure.' when field isn't string / text type.
     *      - May require type switching.
     *      - Have used 9999999999 instead for now.
     * TODO: Is there anything that can be done for denormalised foreign keys?
     *      - E.g. if parent & child foreign keys don't match.
     */

    protected $method = 'PUT';

    protected $originalAttributes = [
        // property => value,
    ];

    protected $dirtyAttributes = [
        // property => value,
    ];

    protected $databaseHas = [
        // property => value,
    ];

    protected $immutable = [
        // property,
    ];

    protected $immutableIfRelations = [
        // property => [relation],
    ];

    protected $syncBelongsToManyRelations = [
        // belongsToManyRelation,
    ];

    protected $syncHasManyRelations = [
        // hasManyRelation,
    ];

    protected function setUp(): void
    {
        if (is_null($this->resource)) {
            $childClassName = array_last(explode('\\', get_class(new static())));
            $modelClassName = str_replace(['Update', 'ActionTest'], '', $childClassName);
            $this->resource = snake_case(str_plural($modelClassName));
        }

        if (is_null($this->modelClassName)) {
            $this->modelClassName = studly_case(str_singular($this->resource));
        }

        parent::setUp();
    }

    /** @test */
    function updates_and_returns_data()
    {
        $model = factory($this->modelFullClassName)->create($this->originalAttributes);
        $attributes = factory($this->modelFullClassName, 'api-update')->raw($this->dirtyAttributes);

        $response = $this->callRoute($model->getKey(), $attributes);

        $attributes = array_merge($attributes, [$model->getKeyName() => $model->getKey()], $this->databaseHas);

        $this->assertDatabaseHas($model->getTable(), $attributes);
        $this->assertDatabaseMissing($model->getTable(), $model->getAttributes());

        $response
            ->assertStatus(200)
            ->assertExactJson(['data' => $model->fresh()->toArray()]);
    }

    /** @test */
    function guards_immutable_properties()
    {
        if (! $this->immutable) $this->markTestSkipped();

        foreach ($this->immutable as $property) {

            $model = factory($this->modelFullClassName)->create($this->originalAttributes);
            $attributes[$property] = 'Something obscure.';

            $response = $this->callRoute($model->getKey(), $attributes);

            $this->assertDatabaseMissing($model->getTable(), [
                $model->getKeyName() => $model->getKey(),
                $property => 'Something obscure.',
            ]);

            $this->assertDatabaseHas($model->getTable(), $model->getAttributes());

            $response->assertStatus(409);
        }
    }

    /** @test */
    function guards_immutable_properties_if_relations()
    {
        if (! $this->immutableIfRelations) $this->markTestSkipped();

        foreach ($this->immutableIfRelations as $property => $relations) {

            foreach ($relations as $relation) {

                $model = factory($this->modelFullClassName)->create($this->originalAttributes);

                switch (get_class($model->$relation())) {

                    case BelongsToMany::class:

                        $model->$relation()->attach(
                            factory(get_class($model->$relation()->getRelated()))->create()
                        );

                        break;

                    case HasOne::class:
                    case HasMany::class:

                        factory(get_class($model->$relation()->getRelated()))->create([
                            $model->$relation()->getForeignKeyName() => $model->getKey(),
                        ]);

                        break;
                }

                $attributes[$property] = 9999999999;

                $response = $this->callRoute($model->getKey(), $attributes);

                $this->assertDatabaseMissing($model->getTable(), [
                    $model->getKeyName() => $model->getKey(),
                    $property => 9999999999,
                ]);

                $this->assertDatabaseHas($model->getTable(), $model->getAttributes());

                $response->assertStatus(409);
            }
        }
    }

    /** @test */
    function syncs_belongs_to_many_relations()
    {
        if (! $this->syncBelongsToManyRelations) $this->markTestSkipped();

        $model = factory($this->modelFullClassName)->create($this->originalAttributes);
        $data = $attributes = factory($this->modelFullClassName, 'api-update')->raw($this->dirtyAttributes);

        $belongsToManyRelationData = [];
        foreach ($this->syncBelongsToManyRelations as $belongsToManyRelation) {
            $belongsToManyModel = $model->$belongsToManyRelation()->getRelated();
            $belongsToManyModels = factory(get_class($belongsToManyModel), 9)->create();
            $model->$belongsToManyRelation()->attach($belongsToManyModels->slice(3));
            $belongsToManyRelationData[$belongsToManyRelation] = $belongsToManyModels;
            $data[snake_case($belongsToManyRelation)] = $belongsToManyModels->take(6)->pluck('id')->all();
        }

        $response = $this->callRoute($model->getKey(), $data);

        $attributes = array_merge($attributes, [$model->getKeyName() => $model->getKey()], $this->databaseHas);

        $this->assertDatabaseHas($model->getTable(), $attributes);
        $this->assertDatabaseMissing($model->getTable(), $model->getAttributes());

        foreach ($this->syncBelongsToManyRelations as $belongsToManyRelation) {
            $this->assertEquals(6, $model->$belongsToManyRelation()->count());
            foreach ($belongsToManyRelationData[$belongsToManyRelation]->take(6) as $belongsToManyModel) {
                $this->assertDatabaseHas($model->$belongsToManyRelation()->getTable(), [
                    $model->$belongsToManyRelation()->getForeignPivotKeyName() => $model->getKey(),
                    $model->$belongsToManyRelation()->getRelatedPivotKeyName() => $belongsToManyModel->getKey(),
                ]);
            }
            foreach ($belongsToManyRelationData[$belongsToManyRelation]->slice(6) as $belongsToManyModel) {
                $this->assertDatabaseMissing($model->$belongsToManyRelation()->getTable(), [
                    $model->$belongsToManyRelation()->getForeignPivotKeyName() => $model->getKey(),
                    $model->$belongsToManyRelation()->getRelatedPivotKeyName() => $belongsToManyModel->getKey(),
                ]);
            }
        }

        $response
            ->assertStatus(200)
            ->assertExactJson(['data' => $model->fresh()->toArray()]);
    }

    /** @test */
    function deletes_belongs_to_many_relations_when_empty_array()
    {
        if (! $this->syncBelongsToManyRelations) $this->markTestSkipped();

        $model = factory($this->modelFullClassName)->create($this->originalAttributes);
        $data = $attributes = factory($this->modelFullClassName, 'api-update')->raw($this->dirtyAttributes);

        $belongsToManyRelationData = [];
        foreach ($this->syncBelongsToManyRelations as $belongsToManyRelation) {
            $belongsToManyModel = $model->$belongsToManyRelation()->getRelated();
            $belongsToManyModels = factory(get_class($belongsToManyModel), 9)->create();
            $model->$belongsToManyRelation()->attach($belongsToManyModels->slice(3));
            $belongsToManyRelationData[$belongsToManyRelation] = $belongsToManyModels;
            $data[snake_case($belongsToManyRelation)] = [];
        }

        $response = $this->callRoute($model->getKey(), $data);

        $attributes = array_merge($attributes, [$model->getKeyName() => $model->getKey()], $this->databaseHas);

        $this->assertDatabaseHas($model->getTable(), $attributes);
        $this->assertDatabaseMissing($model->getTable(), $model->getAttributes());

        foreach ($this->syncBelongsToManyRelations as $belongsToManyRelation) {
            $this->assertEquals(0, $model->$belongsToManyRelation()->count());
            foreach ($belongsToManyRelationData[$belongsToManyRelation] as $belongsToManyModel) {
                $this->assertDatabaseMissing($model->$belongsToManyRelation()->getTable(), [
                    $model->$belongsToManyRelation()->getForeignPivotKeyName() => $model->getKey(),
                    $model->$belongsToManyRelation()->getRelatedPivotKeyName() => $belongsToManyModel->getKey(),
                ]);
            }
        }

        $response
            ->assertStatus(200)
            ->assertExactJson(['data' => $model->fresh()->toArray()]);
    }

    /** @test */
    function ignores_belongs_to_many_relations_when_null()
    {
        if (! $this->syncBelongsToManyRelations) $this->markTestSkipped();

        $model = factory($this->modelFullClassName)->create($this->originalAttributes);
        $data = $attributes = factory($this->modelFullClassName, 'api-update')->raw($this->dirtyAttributes);

        $belongsToManyRelationData = [];
        foreach ($this->syncBelongsToManyRelations as $belongsToManyRelation) {
            $belongsToManyModel = $model->$belongsToManyRelation()->getRelated();
            $belongsToManyModels = factory(get_class($belongsToManyModel), 9)->create();
            $model->$belongsToManyRelation()->attach($belongsToManyModels->slice(3));
            $belongsToManyRelationData[$belongsToManyRelation] = $belongsToManyModels;
            $data[snake_case($belongsToManyRelation)] = null;
        }

        $response = $this->callRoute($model->getKey(), $data);

        $attributes = array_merge($attributes, [$model->getKeyName() => $model->getKey()], $this->databaseHas);

        $this->assertDatabaseHas($model->getTable(), $attributes);
        $this->assertDatabaseMissing($model->getTable(), $model->getAttributes());

        foreach ($this->syncBelongsToManyRelations as $belongsToManyRelation) {
            $this->assertEquals(6, $model->$belongsToManyRelation()->count());
            foreach ($belongsToManyRelationData[$belongsToManyRelation]->slice(3) as $belongsToManyModel) {
                $this->assertDatabaseHas($model->$belongsToManyRelation()->getTable(), [
                    $model->$belongsToManyRelation()->getForeignPivotKeyName() => $model->getKey(),
                    $model->$belongsToManyRelation()->getRelatedPivotKeyName() => $belongsToManyModel->getKey(),
                ]);
            }
            foreach ($belongsToManyRelationData[$belongsToManyRelation]->take(3) as $belongsToManyModel) {
                $this->assertDatabaseMissing($model->$belongsToManyRelation()->getTable(), [
                    $model->$belongsToManyRelation()->getForeignPivotKeyName() => $model->getKey(),
                    $model->$belongsToManyRelation()->getRelatedPivotKeyName() => $belongsToManyModel->getKey(),
                ]);
            }
        }

        $response
            ->assertStatus(200)
            ->assertExactJson(['data' => $model->fresh()->toArray()]);
    }

    /** @test */
    function ignores_belongs_to_many_relations_when_not_given()
    {
        if (! $this->syncBelongsToManyRelations) $this->markTestSkipped();

        $model = factory($this->modelFullClassName)->create($this->originalAttributes);
        $attributes = factory($this->modelFullClassName, 'api-update')->raw($this->dirtyAttributes);

        $belongsToManyRelationData = [];
        foreach ($this->syncBelongsToManyRelations as $belongsToManyRelation) {
            $belongsToManyModel = $model->$belongsToManyRelation()->getRelated();
            $belongsToManyModels = factory(get_class($belongsToManyModel), 9)->create();
            $model->$belongsToManyRelation()->attach($belongsToManyModels->slice(3));
            $belongsToManyRelationData[$belongsToManyRelation] = $belongsToManyModels;
        }

        $response = $this->callRoute($model->getKey(), $attributes);

        $attributes = array_merge($attributes, [$model->getKeyName() => $model->getKey()], $this->databaseHas);

        $this->assertDatabaseHas($model->getTable(), $attributes);
        $this->assertDatabaseMissing($model->getTable(), $model->getAttributes());

        foreach ($this->syncBelongsToManyRelations as $belongsToManyRelation) {
            $this->assertEquals(6, $model->$belongsToManyRelation()->count());
            foreach ($belongsToManyRelationData[$belongsToManyRelation]->slice(3) as $belongsToManyModel) {
                $this->assertDatabaseHas($model->$belongsToManyRelation()->getTable(), [
                    $model->$belongsToManyRelation()->getForeignPivotKeyName() => $model->getKey(),
                    $model->$belongsToManyRelation()->getRelatedPivotKeyName() => $belongsToManyModel->getKey(),
                ]);
            }
            foreach ($belongsToManyRelationData[$belongsToManyRelation]->take(3) as $belongsToManyModel) {
                $this->assertDatabaseMissing($model->$belongsToManyRelation()->getTable(), [
                    $model->$belongsToManyRelation()->getForeignPivotKeyName() => $model->getKey(),
                    $model->$belongsToManyRelation()->getRelatedPivotKeyName() => $belongsToManyModel->getKey(),
                ]);
            }
        }

        $response
            ->assertStatus(200)
            ->assertExactJson(['data' => $model->fresh()->toArray()]);
    }

    /** @test */
    function syncs_has_many_relations()
    {
        if (! $this->syncHasManyRelations) $this->markTestSkipped();

        $model = factory($this->modelFullClassName)->create($this->originalAttributes);
        $attributes = factory($this->modelFullClassName, 'api-update')->raw($this->dirtyAttributes);

        $createdHasManyRelationData = $this->createHasManyRelationData($model);
        $madeHasManyRelationData = $this->makeHasManyRelationData($model, $createdHasManyRelationData);

        $response = $this->callRoute($model->getKey(), array_merge($attributes, $madeHasManyRelationData));

        $attributes = array_merge($attributes, [$model->getKeyName() => $model->getKey()], $this->databaseHas);

        $this->assertDatabaseHas($model->getTable(), $attributes);
        $this->assertDatabaseMissing($model->getTable(), $model->getAttributes());

        $this->assertHasManyRelationDataSynced($model, $createdHasManyRelationData, $madeHasManyRelationData);

        $response
            ->assertStatus(200)
            ->assertExactJson(['data' => $model->fresh()->toArray()]);
    }

    /** @test */
    function deletes_has_many_relations_when_empty_array()
    {
        if (! $this->syncHasManyRelations) $this->markTestSkipped();

        $model = factory($this->modelFullClassName)->create($this->originalAttributes);
        $attributes = factory($this->modelFullClassName, 'api-update')->raw($this->dirtyAttributes);

        $createdHasManyRelationData = $this->createHasManyRelationData($model);

        $madeHasManyRelationData = [];
        foreach ($this->syncHasManyRelations as $hasManyRelation) {
            $madeHasManyRelationData[snake_case($hasManyRelation)] = [];
        }

        $response = $this->callRoute($model->getKey(), array_merge($attributes, $madeHasManyRelationData));

        $attributes = array_merge($attributes, [$model->getKeyName() => $model->getKey()], $this->databaseHas);

        $this->assertDatabaseHas($model->getTable(), $attributes);
        $this->assertDatabaseMissing($model->getTable(), $model->getAttributes());

        foreach ($this->syncHasManyRelations as $hasManyRelation) {
            $this->assertEquals(0, $model->$hasManyRelation()->count());
            $createdHasManyRelationData->get($hasManyRelation)->each(function ($hasManyModel) {
                $this->assertDeleted($hasManyModel);
            });
        }

        $response
            ->assertStatus(200)
            ->assertExactJson(['data' => $model->fresh()->toArray()]);
    }

    /** @test */
    function ignores_has_many_relations_when_null()
    {
        if (! $this->syncHasManyRelations) $this->markTestSkipped();

        $model = factory($this->modelFullClassName)->create($this->originalAttributes);
        $attributes = factory($this->modelFullClassName, 'api-update')->raw($this->dirtyAttributes);

        $createdHasManyRelationData = $this->createHasManyRelationData($model);

        $madeHasManyRelationData = [];
        foreach ($this->syncHasManyRelations as $hasManyRelation) {
            $madeHasManyRelationData[snake_case($hasManyRelation)] = null;
        }

        $response = $this->callRoute($model->getKey(), array_merge($attributes, $madeHasManyRelationData));

        $attributes = array_merge($attributes, [$model->getKeyName() => $model->getKey()], $this->databaseHas);

        $this->assertDatabaseHas($model->getTable(), $attributes);
        $this->assertDatabaseMissing($model->getTable(), $model->getAttributes());

        foreach ($this->syncHasManyRelations as $hasManyRelation) {
            $this->assertEquals(10, $model->$hasManyRelation()->count());
            $createdHasManyRelationData->get($hasManyRelation)->each(function ($hasManyModel) {
                $this->assertNotDeleted($hasManyModel);
            });
        }

        $response
            ->assertStatus(200)
            ->assertExactJson(['data' => $model->fresh()->toArray()]);
    }

    /** @test */
    function ignores_has_many_relations_when_not_given()
    {
        if (! $this->syncHasManyRelations) $this->markTestSkipped();

        $model = factory($this->modelFullClassName)->create($this->originalAttributes);
        $attributes = factory($this->modelFullClassName, 'api-update')->raw($this->dirtyAttributes);

        $createdHasManyRelationData = $this->createHasManyRelationData($model);

        $response = $this->callRoute($model->getKey(), $attributes);

        $attributes = array_merge($attributes, [$model->getKeyName() => $model->getKey()], $this->databaseHas);

        $this->assertDatabaseHas($model->getTable(), $attributes);
        $this->assertDatabaseMissing($model->getTable(), $model->getAttributes());

        foreach ($this->syncHasManyRelations as $hasManyRelation) {
            $this->assertEquals(10, $model->$hasManyRelation()->count());
            $createdHasManyRelationData->get($hasManyRelation)->each(function ($hasManyModel) {
                $this->assertNotDeleted($hasManyModel);
            });
        }

        $response
            ->assertStatus(200)
            ->assertExactJson(['data' => $model->fresh()->toArray()]);
    }

    protected function createHasManyRelationData(Model $model)
    {
        $createdHasManyRelationData = [];

        foreach ($this->syncHasManyRelations as $hasManyRelation) {

            $hasManyModel = $model->$hasManyRelation()->getRelated();

            $createdHasManyRelationData[$hasManyRelation] = factory(get_class($hasManyModel), 10)->create([
                $model->$hasManyRelation()->getForeignKeyName() => $model->getKey(),
            ]);
        }

        return collect($createdHasManyRelationData);
    }

    protected function makeHasManyRelationData(Model $model, Collection $createdHasManyRelationData)
    {
        $madeHasManyRelationData = [];

        foreach ($this->syncHasManyRelations as $hasManyRelation) {

            $hasManyModel = $model->$hasManyRelation()->getRelated();

            $newHasManyModels = factory(get_class($hasManyModel), 'api-create', 5)->raw();
            $existingHasManyModels = $createdHasManyRelationData[$hasManyRelation]->take(5);
            $changedHasManyModels = [];
            foreach ($existingHasManyModels as $existingHasManyModel) {
                $changedHasManyModels[] = factory(get_class($hasManyModel), 'api-update')->raw([
                    $hasManyModel->getKeyName() => $existingHasManyModel->getKey(),
                    $model->$hasManyRelation()->getForeignKeyName() => $model->getKey(),
                ]);
            }

            $madeHasManyRelationData[snake_case($hasManyRelation)] = array_merge($newHasManyModels, $changedHasManyModels);
        }

        return $madeHasManyRelationData;
    }

    protected function assertHasManyRelationDataSynced(Model $model, Collection $createdHasManyRelationData, array $madeHasManyRelationData)
    {
        foreach ($this->syncHasManyRelations as $hasManyRelation) {

            $hasManyModel = $model->$hasManyRelation()->getRelated();

            $this->assertEquals(10, $model->$hasManyRelation()->count());

            foreach ($madeHasManyRelationData[snake_case($hasManyRelation)] as $index => $attributes) {

                if ($index >= 5) {
                    $attributes = array_add(
                        $attributes,
                        $hasManyModel->getKeyName(),
                        $createdHasManyRelationData[$hasManyRelation]->get($index-5)->getKey()
                    );
                }

                $this->assertDatabaseHas($hasManyModel->getTable(), array_merge($attributes, [
                    $model->$hasManyRelation()->getForeignKeyName() => $model->getKey(),
                ]));
            }

            foreach ($createdHasManyRelationData[$hasManyRelation]->take(5) as $hasManyModel) {
                $this->assertDatabaseMissing($hasManyModel->getTable(), $hasManyModel->getAttributes());
            }

            foreach ($createdHasManyRelationData[$hasManyRelation]->slice(5) as $hasManyModel) {
                $this->assertDeleted($hasManyModel);
            }
        }
    }
}
