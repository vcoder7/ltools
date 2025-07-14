<?php

namespace Vcoder7\Ltools\Tests\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Orchestra\Testbench\TestCase;
use Vcoder7\Ltools\Http\Traits\CreateUuidTrait;
use Vcoder7\Ltools\Models\ChangelogItem;
use Vcoder7\Ltools\Services\ChangelogService;

class ChangelogServiceTest extends TestCase
{
    private ChangelogService $testService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['db']->connection()->getSchemaBuilder()->create('ltools_changelog_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('model_id');
            $table->string('model');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('changes');
            $table->uuid()->unique();
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('customers', function ($table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->uuid()->unique();
            $table->timestamps();
        });

        $this->testService = new ChangelogService();
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('ltools.global_excluded_changelog_fields', ['updated_at', 'created_at']);
        $app['config']->set('ltools.table_name_changelog_items', 'ltools_changelog_items');
    }

    public function test_it_creates_changelog_item_from_model(): void
    {
        $model = new Customer([
            'first_name' => 'John',
            'last_name' => 'Smith'
        ]);
        $model->id = 42;

        $result = $this->testService->create($model);

        $this->assertDatabaseHas('ltools_changelog_items', [
            'model_id' => 42,
            'model' => Customer::class,
        ]);

        $this->assertEquals([
            'first_name' => 'John',
            'last_name' => 'Smith',
            'id' => 42,
        ], $result->changes);

        $this->assertNull($result->user_id);
    }

    public function test_it_does_not_save_when_there_are_no_changes(): void
    {
        $customer = Customer::create(['first_name' => 'Lolly']);

        $result = $this->testService->update($customer);

        $this->assertNull($result);
        $this->assertDatabaseCount('ltools_changelog_items', 0);
    }

    public function test_it_saves_diff_when_there_are_changes(): void
    {
        $user = new TestUser(['id' => 1]);
        Auth::shouldReceive('user')->andReturn($user);

        $customer = new Customer(['first_name' => 'Tom', 'last_name' => 'Smith']);
        $customer->save();

        $customer->syncOriginal();

        $customer->first_name = 'John';
        $customer->last_name = 'Doe';

        $result = $this->testService->update($customer);

        $customer->save();

        $this->assertInstanceOf(ChangelogItem::class, $result);

        $this->assertDatabaseHas('ltools_changelog_items', [
            'model_id' => $customer->id,
            'model' => Customer::class,
            'user_id' => 1,
        ]);

        $this->assertEquals([
            'first_name' => [
                'old_value' => 'Tom',
                'new_value' => 'John',
            ],
            'last_name' => [
                'old_value' => 'Smith',
                'new_value' => 'Doe',
            ]
        ], $result->changes);
    }
}

class Customer extends Model
{
    use CreateUuidTrait;
    protected $table = 'customers';
    protected $guarded = [];
    public $timestamps = true;
    public array $excludedChangelogFields = [];
}

class TestUser
{
    public function __construct(public array $attributes = [])
    {
    }

    public function __get($key)
    {
        return $this->attributes[$key] ?? null;
    }
}
