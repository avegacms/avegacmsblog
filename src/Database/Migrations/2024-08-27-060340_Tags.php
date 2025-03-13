<?php

declare(strict_types=1);

namespace AvegaCmsBlog\Database\Migrations;

use AvegaCms\Utilities\Migrator;
use CodeIgniter\Database\Migration;

class Tags extends Migration
{
    public function up(): void
    {
        /**
         * Creation of TAGS table
         */
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
            ],
            'slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'active' => [
                'type'    => 'TINYINT',
                'default' => 0,
            ],
            ...Migrator::byId(),
            ...Migrator::dateFields(['deleted_at']),
        ]);
        $this->forge->addPrimaryKey('id');

        $this->forge->createTable('tags');

        /**
         * Creation of TAGS_LINKS table
         */
        $this->forge->addField([
            'tag_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'meta_id' => [
                'type'       => 'BIGINT',
                'constraint' => 16,
                'unsigned'   => true,
            ],
            ...Migrator::byId(),
            ...Migrator::dateFields(['deleted_at']),
        ]);
        $this->forge->addUniqueKey(['tag_id', 'meta_id']);

        $this->forge->addForeignKey('tag_id', 'tags', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('meta_id', 'metadata', 'id', '', 'CASCADE');

        $this->forge->createTable('tags_links');
    }

    public function down(): void
    {
        $this->forge->dropTable('tags');
        $this->forge->dropTable('tags_links');
    }
}
