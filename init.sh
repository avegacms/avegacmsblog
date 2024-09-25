#!/bin/bash
php spark migrate:rollback
php spark migrate -all
php spark db:seed AvegaCms\\Database\\Seeds\\AvegaCmsInstallSeeder
php spark db:seed AvegaCmsBlog\\Database\\Seeds\\InstallModuleSeeder
php spark db:seed AvegaCmsBlog\\Database\\Seeds\\TestingSeeder

