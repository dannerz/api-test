<?php

namespace Dannerz\ApiTest;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Sota\ApiTest\Tests\ActionTest;

abstract class DeleteModelActionTest extends ActionTest
{
    /*
     * TODO: Apply 'surrounding asserts' to each test (ensure all surrounding data wasn't affected).
     * TODO: Review the deleting of models within tests to clear out for next loop.
     * TODO: May not need => 409 declaration. Probably better off defining what it should always be and keeping it.
     * TODO: Could do with $databaseHas (e.g. for event of setting a deleted by user).
     */

    protected $method = 'DELETE';

    protected $attributes = [
        // property => value,
    ];

    protected $guardedByRelations = [
        // relation => httpErrorCode : int,
    ];

    protected $cascadeRelations = [
        // relation,
    ];

    protected function setUp(): void
    {
        if (is_null($this->resource)) {
            $childClassName = array_last(explode('\\', get_class(new static())));
            $modelClassName = str_replace(['Delete', 'ActionTest'], '', $childClassName);
            $this->resource = snake_case(str_plural($modelClassName));
        }

        if (is_null($this->modelClassName)) {
            $this->modelClassName = studly_case(str_singular($this->resource));
        }

        parent::setUp();
    }

    /** @test */
    function deletes()
    {
        $model = factory($this->modelFullClassName)->create($this->attributes);

        $response = $this->callRoute($model->getKey());

        $this->assertDeleted($model);

        $response->assertStatus(200)->assertExactJson([]);
    }

    /** @test */
    function is_guarded_by_relations()
    {
        if (! $this->guardedByRelations) $this->markTestSkipped();

        foreach ($this->guardedByRelations as $relation => $httpErrorCode) {

            $model = factory($this->modelFullClassName)->create($this->attributes);

            switch (get_class($model->$relation())) {

                case BelongsToMany::class:

                    $belongsToManyModel = factory(get_class($model->$relation()->getRelated()))->create();

                    $model->$relation()->attach($belongsToManyModel);

                    $response = $this->callRoute($model->getKey());

                    $this->assertNotDeleted($model);

                    $response->assertStatus($httpErrorCode);

                    $belongsToManyModel->delete();

                    break;

                case HasOne::class:
                case HasMany::class:

                    $hasModel = factory(get_class($model->$relation()->getRelated()))->create([
                        $model->$relation()->getForeignKeyName() => $model->getKey(),
                    ]);

                    $response = $this->callRoute($model->getKey());

                    $this->assertNotDeleted($model);

                    $response->assertStatus($httpErrorCode);

                    $hasModel->delete();

                    break;
            }
        }
    }

    /** @test */
    function cascades_relations()
    {
        if (! $this->cascadeRelations) $this->markTestSkipped();

        foreach ($this->cascadeRelations as $relation) {

            $model = factory($this->modelFullClassName)->create($this->attributes);

            switch (get_class($model->$relation())) {

                case BelongsToMany::class:

                    $belongsToManyModels = factory(get_class($model->$relation()->getRelated()), 5)->create();

                    $model->$relation()->attach($belongsToManyModels);

                    $response = $this->callRoute($model->getKey());

                    $this->assertDeleted($model);

                    $this->assertEquals(0, $model->$relation()->count());

                    foreach ($belongsToManyModels as $belongsToManyModel) {

                        $this->assertDatabaseMissing($model->$relation()->getTable(), [
                            $model->$relation()->getForeignPivotKeyName() => $model->getKey(),
                            $model->$relation()->getRelatedPivotKeyName() => $belongsToManyModel->getKey(),
                        ]);
                    }

                    $response->assertStatus(200)->assertExactJson([]);

                    break;

                case HasOne::class:
                case HasMany::class:

                    $hasModel = factory(get_class($model->$relation()->getRelated()))->create([
                        $model->$relation()->getForeignKeyName() => $model->getKey(),
                    ]);

                    $response = $this->callRoute($model->getKey());

                    $this->assertDeleted($model);

                    $this->assertEquals(0, $model->$relation()->count());

                    $this->assertDeleted($hasModel);

                    $response->assertStatus(200)->assertExactJson([]);

                    break;
            }
        }
    }
}
