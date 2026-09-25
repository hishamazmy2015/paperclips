<?php

use App\Platform\EventPartitions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Spec §8 — 017 events, partitioned monthly (PostgreSQL declarative partitioning). Funnel and
// product events (§13). Monthly partitions are created ahead of time by
// `platform:events:partitions`; a DEFAULT partition guarantees no write is ever lost.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE events (
                id          bigserial,
                name        varchar(80)  NOT NULL,
                tenant_id   bigint       NULL,
                account_id  bigint       NULL,
                user_id     bigint       NULL,
                session_id  varchar(64)  NULL,
                properties  jsonb        NULL,
                device      varchar(16)  NULL,
                locale      varchar(5)   NULL,
                ip          varchar(45)  NULL,
                created_at  timestamptz  NOT NULL DEFAULT now(),
                PRIMARY KEY (id, created_at)
            ) PARTITION BY RANGE (created_at)
        SQL);
        DB::statement('CREATE INDEX events_name_created_at_idx ON events (name, created_at)');
        DB::statement('CREATE INDEX events_tenant_id_created_at_idx ON events (tenant_id, created_at)');
        DB::statement('CREATE INDEX events_account_id_created_at_idx ON events (account_id, created_at)');
        DB::statement('CREATE TABLE events_default PARTITION OF events DEFAULT');

        EventPartitions::ensureMonths(now(), 2);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS events CASCADE');
    }
};
