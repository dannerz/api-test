<?php

namespace Dannerz\ApiTest;

abstract class FindModelActionTest extends ActionTest
{
    /*
     * TODO: Includes, fields & appends.
     */

    protected $method = 'GET';

    protected function setUp(): void
    {
        if (is_null($this->resource)) {
            $childClassName = array_last(explode('\\', get_class(new static())));
            $modelClassName = str_replace(['Find', 'ActionTest'], '', $childClassName);
            $this->resource = snake_case(str_plural($modelClassName));
        }

        if (is_null($this->modelClassName)) {
            $this->modelClassName = studly_case(str_singular($this->resource));
        }

        parent::setUp();
    }

    /** @test */
    function finds_and_returns_data()
    {
        $model = factory($this->modelFullClassName)->create();
        factory($this->modelFullClassName, 15)->create();

        $response = $this->callRoute($model->getKey());

        $response
            ->assertStatus(200)
            ->assertExactJson(['data' => $model->fresh()->toArray()]);
    }
}
