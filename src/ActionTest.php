<?php

namespace Dannerz\ApiTest;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class ActionTest extends TestCase
{
    /*
     * TODO: Consider multiple create / delete / update route.
     * TODO: Inclusion of all relation types in test files (atm it's just BelongsTo, BelongsToMany & HasMany etc).
     */

    use DatabaseTransactions;

    protected $modelNamespace = 'App\Models';

    protected $userModelClassName = 'User';

    protected $prefix = 'api';

    protected $method; // must be specified by a child class

    protected $resource; // must be specified by a child class

    protected $path;

    protected $data = [];

    protected $user; // if not specified by a child class, will be mocked here

    protected $modelClassName; // if specified by a child class, model can be inferred

    protected $modelFullClassName;

    protected $model;

    protected function setUp(): void
    {
        if ($this->modelClassName) {
            $this->modelFullClassName = $this->modelNamespace.'\\'.$this->modelClassName;
            $this->model = new $this->modelFullClassName();
        }

        parent::setUp();
    }

    protected function callRoute($path = null, $data = null, $user = null)
    {
        $path = $path ?: $this->path;

        $data = $data ?: $this->data;

        $user = $user ?: ($this->user ?: factory($this->modelNamespace.'\\'.$this->userModelClassName)->create());

        $uri = $this->prefix.'/'.$this->resource.($path ? '/'.$path : null);

        $this->actingAs($user);

        return $this->json($this->method, $uri, $data);
    }

    protected function callRouteWithoutPath($data, $user = null)
    {
        return $this->callRoute(null, $data, $user);
    }

    protected function callRouteWithoutData($path, $user)
    {
        return $this->callRoute($path, null, $user);
    }

    protected function emptyModelTable()
    {
        if ($this->model) DB::table($this->model->getTable())->delete();
    }

    protected function assertDatabaseHas($table, array $data, $connection = null)
    {
        foreach ($data as $property => $value) {
            if (is_string($value)) {
                $value = json_decode($value);
            }
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
                $data[$property] = DB::raw("CAST('{$value}' AS JSON)");
            }
        }

        return parent::assertDatabaseHas($table, $data, $connection);
    }

    protected function assertDeleted(Model $model)
    {
        $params = [$model->getTable(), [$model->getKeyName() => $model->getKey()]];

        if (method_exists($model, 'getDeletedAtColumn')) {
            $this->assertSoftDeleted(...$params);
        } else {
            $this->assertDatabaseMissing(...$params);
        }
    }

    protected function assertNotDeleted(Model $model)
    {
        $attributes = [$model->getKeyName() => $model->getKey()];

        if (method_exists($model, 'getDeletedAtColumn')) {
            $attributes[$model->getDeletedAtColumn()] = null;
        }

        $this->assertDatabaseHas($model->getTable(), $attributes);
    }
}
