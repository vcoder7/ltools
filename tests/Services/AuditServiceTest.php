<?php

namespace Vcoder7\Ltools\Tests\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Orchestra\Testbench\TestCase;
use Vcoder7\Ltools\Enums\AuditEventEnum;
use Vcoder7\Ltools\Facades\Audit;
use Vcoder7\Ltools\Http\Traits\Auditable;
use Vcoder7\Ltools\Http\Traits\CreateUuidTrait;
use Vcoder7\Ltools\Models\AuditLog;
use Vcoder7\Ltools\PackageServiceProvider;
use Vcoder7\Ltools\Services\Audit\AuditChainVerifier;

class AuditServiceTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PackageServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('ltools.audit.table_name', 'audit_logs');
        $app['config']->set('ltools.audit.enabled', true);
        $app['config']->set('ltools.audit.capture_context', true);
        $app['config']->set('ltools.audit.console_actor', 'system');
        $app['config']->set('ltools.audit.redacted_fields', ['password']);
        $app['config']->set('ltools.audit.global_excluded_fields', ['created_at', 'updated_at']);
        $app['config']->set('ltools.user_model_class', AuditTestUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $schema = $this->app['db']->connection()->getSchemaBuilder();

        $schema->create('audit_logs', function ($table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('event');
            $table->string('auditable_type')->nullable();
            $table->string('auditable_id')->nullable();
            $table->string('auditable_label')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('actor_type')->nullable();
            $table->bigInteger('actor_id')->nullable();
            $table->string('actor_label')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('url')->nullable();
            $table->string('http_method', 16)->nullable();
            $table->string('request_id')->nullable();
            $table->json('tags')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64);
            $table->timestamp('created_at')->nullable();
        });

        $schema->create('widgets', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });
    }

    public function test_create_event_is_audited_with_new_values_and_genesis_hash(): void
    {
        $widget = Widget::create(['name' => 'Alpha']);

        $audit = AuditLog::query()->latest('id')->first();

        $this->assertNotNull($audit);
        $this->assertSame(AuditEventEnum::Created->value, $audit->event);
        $this->assertSame(Widget::class, $audit->auditable_type);
        $this->assertSame((string) $widget->id, $audit->auditable_id);
        $this->assertSame('Widget: Alpha', $audit->auditable_label);
        $this->assertSame('Alpha', $audit->new_values['name']);
        $this->assertNull($audit->old_values);
        $this->assertNull($audit->prev_hash);
        $this->assertNotEmpty($audit->hash);
        $this->assertTrue($audit->verifyHash());
    }

    public function test_events_form_a_verifiable_hash_chain(): void
    {
        $first = Widget::create(['name' => 'A']);
        $second = Widget::create(['name' => 'B']);

        $audits = AuditLog::query()->orderBy('id')->get();

        $this->assertCount(2, $audits);
        $this->assertNull($audits[0]->prev_hash);
        $this->assertSame($audits[0]->hash, $audits[1]->prev_hash, 'second row links to the first');
        $this->assertTrue($audits[0]->verifyHash());
        $this->assertTrue($audits[1]->verifyHash());
    }

    public function test_update_records_old_and_new_and_redacts_secrets(): void
    {
        Auth::shouldReceive('user')->andReturn(new AuditTestUser(['id' => 7, 'email' => 'a@b.c']));

        $widget = Widget::create(['name' => 'Old', 'password' => 'secret1']);
        $widget->update(['name' => 'New', 'password' => 'secret2']);

        $audit = AuditLog::query()
            ->where('event', AuditEventEnum::Updated->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('Old', $audit->old_values['name']);
        $this->assertSame('New', $audit->new_values['name']);
        $this->assertSame('[REDACTED]', $audit->old_values['password']);
        $this->assertSame('[REDACTED]', $audit->new_values['password']);
        $this->assertSame(7, $audit->actor_id);
        $this->assertSame(AuditTestUser::class, $audit->actor_type);
        $this->assertSame('a@b.c', $audit->actor_label);
        $this->assertTrue($audit->verifyHash());
    }

    public function test_update_with_no_real_change_writes_no_row(): void
    {
        $widget = Widget::create(['name' => 'Same']);
        AuditLog::query()->delete();

        $widget->update(['name' => 'Same']);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_without_auditing_suppresses_writes(): void
    {
        Audit::withoutAuditing(function () {
            Widget::create(['name' => 'Hidden']);
        });

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_disabled_config_suppresses_writes(): void
    {
        $this->app['config']->set('ltools.audit.enabled', false);

        Widget::create(['name' => 'NoAudit']);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_explicit_export_event_is_flagged_sensitive(): void
    {
        $audit = Audit::export(['auditable_label' => 'Payroll June', 'meta' => ['format' => 'csv']]);

        $this->assertSame(AuditEventEnum::Export->value, $audit->event);
        $this->assertTrue($audit->is_sensitive);
        $this->assertSame('csv', $audit->meta['format']);
        $this->assertTrue($audit->verifyHash());
    }

    public function test_tampering_with_a_row_breaks_hash_verification(): void
    {
        Widget::create(['name' => 'Legit']);
        $audit = AuditLog::query()->latest('id')->first();
        $this->assertTrue($audit->verifyHash());

        // Simulate a direct DB edit that bypasses the application.
        AuditLog::query()->where('id', $audit->id)->update([
            'new_values' => json_encode(['name' => 'Tampered']),
        ]);

        $tampered = AuditLog::query()->find($audit->id);

        $this->assertFalse($tampered->verifyHash(), 'a mutated row must fail verification');
    }

    public function test_chain_verifier_reports_intact_chain(): void
    {
        Widget::create(['name' => 'A']);
        Widget::create(['name' => 'B']);
        Widget::create(['name' => 'C']);

        $result = app(AuditChainVerifier::class)->verify();

        $this->assertTrue($result['intact']);
        $this->assertSame(3, $result['checked']);
        $this->assertNull($result['broken_at_id']);
    }

    public function test_chain_verifier_detects_tampered_row(): void
    {
        Widget::create(['name' => 'A']);
        $target = AuditLog::query()->latest('id')->first();
        Widget::create(['name' => 'B']);
        Widget::create(['name' => 'C']);

        // Tamper with the first row's payload (bypassing the app).
        AuditLog::query()->where('id', $target->id)->update([
            'new_values' => json_encode(['name' => 'Hacked']),
        ]);

        $result = app(AuditChainVerifier::class)->verify();

        $this->assertFalse($result['intact']);
        $this->assertSame($target->id, $result['broken_at_id']);
        $this->assertNotNull($result['broken_at_uuid']);
    }
}

class Widget extends Model
{
    use Auditable;

    protected $table = 'widgets';
    protected $guarded = [];
    public $timestamps = true;
}

class AuditTestUser
{
    public function __construct(public array $attributes = [])
    {
    }

    public function __get($key)
    {
        return $this->attributes[$key] ?? null;
    }

    public function getAuthIdentifier()
    {
        return $this->attributes['id'] ?? null;
    }
}
