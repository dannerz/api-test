<?php

namespace Dannerz\ApiTest;

use Illuminate\Database\Eloquent\Model;

abstract class CreateModelActionTest extends ActionTest
{
    /*
     * TODO: Apply 'surrounding asserts' to each test (ensure all surrounding data wasn't affected).
     * TODO: Load relations on return (add to service then to this test).
     * TODO: Syncs arrays can be merged.
     * TODO: Increments > should include deleted? Or not?
     */

    protected $method = 'POST';

    protected $attributes = [
        // property => value,
    ];

    protected $databaseHas = [
        // property => value,
    ];

    protected $increment = [
        // property => :belongsToRelation,
    ];

    protected $incrementUnique = [
        // property => :belongsToRelation,
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
            $modelClassName = str_replace(['Create', 'ActionTest'], '', $childClassName);
            $this->resource = snake_case(str_plural($modelClassName));
        }

        if (is_null($this->modelClassName)) {
            $this->modelClassName = studly_case(str_singular($this->resource));
        }

        parent::setUp();
    }

    /** @test */
    function creates_and_returns_data()
    {
        $attributes = factory($this->modelFullClassName, 'api-create')->raw($this->attributes);

        $response = $this->callRouteWithoutPath($attributes);

        $id = $response->json('data.'.$this->model->getKeyName());

        $attributes = array_merge($attributes, $this->databaseHas);

        $this->assertDatabaseHas($this->model->getTable(), array_add(
            $attributes, $this->model->getKeyName(), $id
        ));

        $response
            ->assertStatus(200)
            ->assertExactJson([
                'data' => $this->model::find($id)->toArray(),
            ]);
    }

    /** @test */
    function increments()
    {
        if (! $this->increment) $this->markTestSkipped();

        $this->incrementsProperty($this->increment);
    }

    /** @test */
    function increments_unique()
    {
        if (! $this->incrementUnique) $this->markTestSkipped();

        $this->incrementsProperty($this->incrementUnique, true);
    }

    /** @test */
    function syncs_belongs_to_many_relations()
    {
        if (! $this->syncBelongsToManyRelations) $this->markTestSkipped();

        $data = $attributes = factory($this->modelFullClassName, 'api-create')->raw($this->attributes);

        $belongsToManyRelationData = [];
        foreach ($this->syncBelongsToManyRelations as $belongsToManyRelation) {
            $belongsToManyModel = $this->model->$belongsToManyRelation()->getRelated();
            $belongsToManyModels = factory(get_class($belongsToManyModel), 10)->create();
            $belongsToManyRelationData[$belongsToManyRelation] = $belongsToManyModels;
            $data[snake_case($belongsToManyRelation)] = $belongsToManyModels->slice(5)->pluck('id')->all();
        }

        $response = $this->callRouteWithoutPath($data);

        $id = $response->json('data.'.$this->model->getKeyName());

        $model = $this->model::find($id);

        $attributes = array_merge($attributes, $this->databaseHas);

        $this->assertDatabaseHas($model->getTable(), array_add(
            $attributes, $model->getKeyName(), $model->getKey()
        ));

        foreach ($this->syncBelongsToManyRelations as $belongsToManyRelation) {
            $this->assertEquals(5, $model->$belongsToManyRelation()->count());
            foreach ($belongsToManyRelationData[$belongsToManyRelation]->slice(5) as $belongsToManyModel) {
                $this->assertDatabaseHas($model->$belongsToManyRelation()->getTable(), [
                    $model->$belongsToManyRelation()->getForeignPivotKeyName() => $model->getKey(),
                    $model->$belongsToManyRelation()->getRelatedPivotKeyName() => $belongsToManyModel->getKey(),
                ]);
            }
        }

        $response
            ->assertStatus(200)
            ->assertExactJson([
                'data' => $model->toArray(),
            ]);
    }

    /** @test */
    function syncs_has_many_relations()
    {
        if (! $this->syncHasManyRelations) $this->markTestSkipped();

        $attributes = factory($this->modelFullClassName, 'api-create')->raw($this->attributes);

        $hasManyRelationData = $this->makeHasManyRelationData();

        $response = $this->callRouteWithoutPath(array_merge($attributes, $hasManyRelationData));

        $id = $response->json('data.'.$this->model->getKeyName());

        $model = $this->model::find($id);

        $attributes = array_merge($attributes, $this->databaseHas);

        $this->assertDatabaseHas($model->getTable(), array_add(
            $attributes, $model->getKeyName(), $model->getKey()
        ));

        $this->assertHasManyRelationDataSynced($model, $hasManyRelationData);

        $response
            ->assertStatus(200)
            ->assertExactJson([
                'data' => $model->toArray(),
            ]);
    }

    protected function incrementsProperty($increment, $unique = false)
    {
        foreach ($increment as $property => $belongsToRelation) {

            if (is_int($property)) {

                $property = $belongsToRelation;

                factory($this->modelFullClassName)->create([$property => 9]);
                factory($this->modelFullClassName)->create([$property => 14]);
                factory($this->modelFullClassName)->create([$property => 22])->delete();
                factory($this->modelFullClassName)->create([$property => 1]);
                factory($this->modelFullClassName)->create([$property => 6])->delete();

                $attributes = factory($this->modelFullClassName, 'api-create')->raw($this->attributes);

            } else {

                $belongsToModel = $this->model->$belongsToRelation()->getRelated();
                $belongsToModel = factory(get_class($belongsToModel))->create();

                $foreignKeyName = $this->model->$belongsToRelation()->getForeignKey();

                factory($this->modelFullClassName)->create([$foreignKeyName => $belongsToModel->id, $property => 9]);
                factory($this->modelFullClassName)->create([$foreignKeyName => $belongsToModel->id, $property => 14]);
                factory($this->modelFullClassName)->create([$foreignKeyName => $belongsToModel->id, $property => 22])->delete();
                factory($this->modelFullClassName)->create([$foreignKeyName => $belongsToModel->id, $property => 1]);
                factory($this->modelFullClassName)->create([$foreignKeyName => $belongsToModel->id, $property => 6])->delete();

                // Decoys.
                factory($this->modelFullClassName)->create([$property => 32]);
                factory($this->modelFullClassName)->create([$property => 25])->delete();
                factory($this->modelFullClassName)->create([$property => 23]);

                $attributes = factory($this->modelFullClassName, 'api-create')->raw(array_merge($this->attributes, [
                    $foreignKeyName => $belongsToModel->id,
                ]));
            }

            $response = $this->callRouteWithoutPath($attributes);

            $id = $response->json('data.'.$this->model->getKeyName());

            $attributes = array_merge($attributes, $this->databaseHas);

            $this->assertDatabaseHas($this->model->getTable(), array_merge($attributes, [
                $this->model->getKeyName() => $id,
                $property => $unique ? 23 : 15,
            ]));
        }
    }

    protected function makeHasManyRelationData()
    {
        $hasManyRelationData = [];

        foreach ($this->syncHasManyRelations as $hasManyRelation) {
            $hasManyModel = $this->model->$hasManyRelation()->getRelated();
            $hasManyRelationData[snake_case($hasManyRelation)] = factory(get_class($hasManyModel), 'api-create', 5)->raw();
        }

        return $hasManyRelationData;
    }

    protected function assertHasManyRelationDataSynced(Model $model, array $hasManyRelationData)
    {
        foreach ($this->syncHasManyRelations as $hasManyRelation) {

            $hasManyModel = $model->$hasManyRelation()->getRelated();
            $hasManyModels = $model->$hasManyRelation()->orderBy($hasManyModel->getKeyName())->get();

            $this->assertCount(5, $hasManyModels);

            foreach ($hasManyRelationData[snake_case($hasManyRelation)] as $index => $attributes) {

                $hasManyModel = $hasManyModels[$index];

                $this->assertDatabaseHas($hasManyModel->getTable(), array_merge($attributes, [
                    $hasManyModel->getKeyName() => $hasManyModel->getKey(),
                    $model->$hasManyRelation()->getForeignKeyName() => $model->getKey(),
                ]));
            }
        }
    }
}
