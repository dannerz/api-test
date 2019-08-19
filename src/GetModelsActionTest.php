<?php

namespace Dannerz\ApiTest;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

abstract class GetModelsActionTest extends ActionTest
{
    /*
     * TODO: Searches, includes, fields & appends.
     * TODO: Handling filters & sorting when property is unique.
     * TODO: Could make it so that the test auto-loads filterProps & sortProps based on factories?
     *      - The point here is that the tests shouldn't be tied into the app layer or database layer (only factories).
     * TODO: Doesn't handle all DB column types (check $model->getConnection()->getDoctrineSchemaManager($table)).
     */

    protected $method = 'GET';

    protected $filterProperties = [
        // property => type,
    ];

    protected $sortProperties = [
        // property => type,
    ];

    protected function setUp(): void
    {
        if (is_null($this->resource)) {
            $childClassName = array_last(explode('\\', get_class(new static())));
            $modelClassName = str_replace(['Get', 'ActionTest'], '', $childClassName);
            $this->resource = snake_case(str_plural($modelClassName));
        }

        if (is_null($this->modelClassName)) {
            $this->modelClassName = studly_case(str_singular($this->resource));
        }

        parent::setUp();
    }

    /** @test */
    function returns_all()
    {
        $models = factory($this->modelFullClassName, 20)->create();

        $response = $this->callRoute();

        $response->assertStatus(200)->assertJsonCount(20, 'data');

        $this->assertEquals($models->fresh()->toArray(), $response->json('data'));
    }

    /** @test */
    function filters_properties()
    {
        if (! $this->filterProperties) $this->markTestSkipped();

        $schemaBuilder = DB::getSchemaBuilder();

        $schemaBuilder->disableForeignKeyConstraints();

        foreach ($this->filterProperties as $property) {

            switch ($schemaBuilder->getColumnType($this->model->getTable(), $property)) {

                case 'integer':
                    $values = [1, 2, 3, 4, 5];
                    break;
                case 'decimal':
                    $values = [1.1, 1.2, 1.3, 1.4, 1.5];
                    break;
                case 'string':
                    $values = ['Aaa', 'Bbb', 'Ccc', 'Ddd', 'Eee'];
                    break;
                case 'date':
                    $values = ['2018-01-01', '2018-01-02', '2018-01-03', '2018-01-04', '2018-01-05'];
                    break;
                case 'datetime':
                    $values = [
                        '2018-01-01 00:00:01',
                        '2018-01-01 00:00:02',
                        '2018-01-01 00:00:03',
                        '2018-01-01 00:00:04',
                        '2018-01-01 00:00:05',
                    ];
                    break;
                default:
                    continue 2;
                    break;
            }

            $models1 = factory($this->modelFullClassName, 1)->create([$property => $values[0]]);
            $models2 = factory($this->modelFullClassName, 2)->create([$property => $values[1]]);
            $models3 = factory($this->modelFullClassName, 3)->create([$property => $values[2]]);
            $models4 = factory($this->modelFullClassName, 4)->create([$property => $values[3]]);
            $models5 = factory($this->modelFullClassName, 5)->create([$property => $values[4]]);

            $queryString = '?filter['.$property.']='.$values[0].','.$values[3];

            $response = $this->callRoute($queryString);

            $response->assertStatus(200)->assertJsonCount(5, 'data');

            $this->assertEquals($models1->merge($models4)->fresh()->toArray(), $response->json('data'));

            $this->emptyModelTable();
        }

        $schemaBuilder->enableForeignKeyConstraints();
    }

    /** @test */
    function sorts_properties()
    {
        if (! $this->sortProperties) $this->markTestSkipped();

        $schemaBuilder = DB::getSchemaBuilder();

        $schemaBuilder->disableForeignKeyConstraints();

        foreach ($this->sortProperties as $property) {

            switch ($schemaBuilder->getColumnType($this->model->getTable(), $property)) {

                case 'integer':
                    $values = [1, 2, 3, 4, 5];
                    break;
                case 'decimal':
                    $values = [1.1, 1.2, 1.3, 1.4, 1.5];
                    break;
                case 'string':
                    $values = ['Aaa', 'Bbb', 'Ccc', 'Ddd', 'Eee'];
                    break;
                case 'date':
                    $values = ['2018-01-01', '2018-01-02', '2018-01-03', '2018-01-04', '2018-01-05'];
                    break;
                case 'datetime':
                    $values = [
                        '2018-01-01 00:00:01',
                        '2018-01-01 00:00:02',
                        '2018-01-01 00:00:03',
                        '2018-01-01 00:00:04',
                        '2018-01-01 00:00:05',
                    ];
                    break;
                default:
                    continue 2;
                    break;
            }

            $model2 = factory($this->modelFullClassName)->create([$property => $values[1]]);
            $model1 = factory($this->modelFullClassName)->create([$property => $values[0]]);
            $model3 = factory($this->modelFullClassName)->create([$property => $values[2]]);
            $model4 = factory($this->modelFullClassName)->create([$property => $values[3]]);
            $model5 = factory($this->modelFullClassName)->create([$property => $values[4]]);

            // Asc.
            $queryString = '?sort='.$property;
            $response = $this->callRoute($queryString);
            $response->assertStatus(200);
            $this->assertEquals(
                (new Collection([$model1, $model2, $model3, $model4, $model5]))->fresh()->toArray(),
                $response->json('data')
            );

            // Desc.
            $queryString = '?sort=-'.$property;
            $response = $this->callRoute($queryString);
            $response->assertStatus(200);
            $this->assertEquals(
                (new Collection([$model5, $model4, $model3, $model2, $model1]))->fresh()->toArray(),
                $response->json('data')
            );

            $this->emptyModelTable();
        }

        $schemaBuilder->enableForeignKeyConstraints();
    }
}
