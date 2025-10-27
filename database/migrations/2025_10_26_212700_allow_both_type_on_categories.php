<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE categories MODIFY type ENUM('income','expense','both') NOT NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE categories DROP CONSTRAINT IF EXISTS categories_type_check");
            DB::statement("ALTER TABLE categories ADD CONSTRAINT categories_type_check CHECK (type IN ('income','expense','both'))");
            return;
        }

        if ($driver === 'sqlite') {
            DB::transaction(function () {
                DB::statement('PRAGMA foreign_keys=off');

                DB::statement(<<<SQL
CREATE TABLE categories_new (
    id integer not null primary key autoincrement,
    user_id integer not null,
    name varchar not null,
    type varchar not null CHECK(type IN ('income','expense','both')),
    color varchar(7) not null default '#3B82F6',
    icon varchar null,
    description text null,
    is_active integer not null default 1,
    created_at datetime,
    updated_at datetime,
    foreign key(user_id) references users(id) on delete cascade
)
SQL);

                DB::statement('INSERT INTO categories_new (id, user_id, name, type, color, icon, description, is_active, created_at, updated_at) SELECT id, user_id, name, type, color, icon, description, is_active, created_at, updated_at FROM categories');

                DB::statement('DROP TABLE categories');
                DB::statement('ALTER TABLE categories_new RENAME TO categories');
                DB::statement('CREATE INDEX categories_user_id_type_index ON categories (user_id, type)');
                DB::statement('CREATE INDEX categories_user_id_is_active_index ON categories (user_id, is_active)');

                DB::statement('PRAGMA foreign_keys=on');
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE categories MODIFY type ENUM('income','expense') NOT NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE categories DROP CONSTRAINT IF EXISTS categories_type_check");
            DB::statement("ALTER TABLE categories ADD CONSTRAINT categories_type_check CHECK (type IN ('income','expense'))");
            return;
        }

        if ($driver === 'sqlite') {
            DB::transaction(function () {
                DB::statement('PRAGMA foreign_keys=off');

                DB::statement(<<<SQL
CREATE TABLE categories_old (
    id integer not null primary key autoincrement,
    user_id integer not null,
    name varchar not null,
    type varchar not null CHECK(type IN ('income','expense')),
    color varchar(7) not null default '#3B82F6',
    icon varchar null,
    description text null,
    is_active integer not null default 1,
    created_at datetime,
    updated_at datetime,
    foreign key(user_id) references users(id) on delete cascade
)
SQL);

                DB::statement("INSERT INTO categories_old (id, user_id, name, type, color, icon, description, is_active, created_at, updated_at) SELECT id, user_id, name, CASE WHEN type NOT IN ('income','expense') THEN 'expense' ELSE type END, color, icon, description, is_active, created_at, updated_at FROM categories");

                DB::statement('DROP TABLE categories');
                DB::statement('ALTER TABLE categories_old RENAME TO categories');
                DB::statement('CREATE INDEX categories_user_id_type_index ON categories (user_id, type)');
                DB::statement('CREATE INDEX categories_user_id_is_active_index ON categories (user_id, is_active)');

                DB::statement('PRAGMA foreign_keys=on');
            });
        }
    }
};
