<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Monitoring columns that move from the contracts to the monitors (contract column => monitor column). */
    private const MOVED = [
        'monitor_enabled' => 'enabled',
        'monitor_auth' => 'auth',
        'monitor_auth_user' => 'auth_user',
        'monitor_auth_password' => 'auth_password',
        'monitor_status' => 'status',
        'monitor_status_since' => 'status_since',
        'monitor_checked_at' => 'checked_at',
        'monitor_failures' => 'failures',
        'monitor_response_ms' => 'response_ms',
        'monitor_error' => 'error',
        'ssl_expires_at' => 'ssl_expires_at',
        'ssl_warning_sent_for' => 'ssl_warning_sent_for',
        'monitor_position' => 'position',
    ];

    /**
     * Uptime monitoring becomes its own thing: a monitor can belong to a maintenance contract
     * or stand alone (e.g. an own app). Existing monitoring and its history move over as they are.
     */
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('maintenance_contract_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->boolean('enabled')->default(true);

            // Password protected websites (htpasswd): none | basic | accept_401
            $table->string('auth', 12)->default('none');
            $table->string('auth_user')->nullable();
            $table->text('auth_password')->nullable();          // encrypted

            $table->string('status', 10)->nullable();            // up | down
            $table->timestamp('status_since')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->unsignedTinyInteger('failures')->default(0);
            $table->unsignedInteger('response_ms')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('ssl_expires_at')->nullable();
            $table->date('ssl_warning_sent_for')->nullable();
            $table->unsignedInteger('position')->nullable();     // own order in the monitoring center
            $table->timestamps();
        });

        Schema::table('site_checks', function (Blueprint $table) {
            $table->foreignId('monitor_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Every contract with an address gets its monitor, the checks follow
        foreach (DB::table('maintenance_contracts')->whereNotNull('url')->where('url', '!=', '')->get() as $contract) {
            $values = ['user_id' => $contract->user_id, 'customer_id' => $contract->customer_id, 'maintenance_contract_id' => $contract->id,
                'name' => $contract->name, 'url' => $contract->url, 'created_at' => $contract->created_at, 'updated_at' => now()];

            foreach (self::MOVED as $from => $to) {
                $values[$to] = $contract->{$from};
            }

            $monitorId = DB::table('monitors')->insertGetId($values);
            DB::table('site_checks')->where('maintenance_contract_id', $contract->id)->update(['monitor_id' => $monitorId]);
        }

        DB::table('site_checks')->whereNull('monitor_id')->delete();

        Schema::table('site_checks', function (Blueprint $table) {
            $table->dropForeign(['maintenance_contract_id']);
            $table->dropIndex(['maintenance_contract_id', 'checked_at']);
            $table->dropColumn('maintenance_contract_id');
            $table->index(['monitor_id', 'checked_at']);
        });

        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->dropColumn(array_keys(self::MOVED));
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->boolean('monitor_enabled')->default(true);
            $table->string('monitor_auth', 12)->default('none');
            $table->string('monitor_auth_user')->nullable();
            $table->text('monitor_auth_password')->nullable();
            $table->string('monitor_status', 10)->nullable();
            $table->timestamp('monitor_status_since')->nullable();
            $table->timestamp('monitor_checked_at')->nullable();
            $table->unsignedTinyInteger('monitor_failures')->default(0);
            $table->unsignedInteger('monitor_response_ms')->nullable();
            $table->string('monitor_error')->nullable();
            $table->timestamp('ssl_expires_at')->nullable();
            $table->date('ssl_warning_sent_for')->nullable();
            $table->unsignedInteger('monitor_position')->nullable();
        });

        Schema::table('site_checks', function (Blueprint $table) {
            $table->foreignId('maintenance_contract_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        foreach (DB::table('monitors')->whereNotNull('maintenance_contract_id')->get() as $monitor) {
            $values = [];
            foreach (self::MOVED as $from => $to) {
                $values[$from] = $monitor->{$to};
            }

            DB::table('maintenance_contracts')->where('id', $monitor->maintenance_contract_id)->update($values);
            DB::table('site_checks')->where('monitor_id', $monitor->id)->update(['maintenance_contract_id' => $monitor->maintenance_contract_id]);
        }

        // Standalone monitors have no place in the old structure
        DB::table('site_checks')->whereNull('maintenance_contract_id')->delete();

        Schema::table('site_checks', function (Blueprint $table) {
            $table->dropForeign(['monitor_id']);
            $table->dropIndex(['monitor_id', 'checked_at']);
            $table->dropColumn('monitor_id');
            $table->index(['maintenance_contract_id', 'checked_at']);
        });

        Schema::dropIfExists('monitors');
    }
};
