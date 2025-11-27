<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            ['key' => 'bbs_name', 'value' => 'PUNKTET BBS', 'type' => 'string', 'group' => 'general', 'description' => 'Name of the BBS'],
            ['key' => 'sysop_name', 'value' => 'TERJE', 'type' => 'string', 'group' => 'general', 'description' => 'SysOp name'],
            ['key' => 'bbs_started', 'value' => '2025-11-27', 'type' => 'string', 'group' => 'general', 'description' => 'Date BBS started'],
            ['key' => 'default_locale', 'value' => 'en', 'type' => 'string', 'group' => 'general', 'description' => 'Default language'],
            
            // User settings
            ['key' => 'new_user_credits', 'value' => '100', 'type' => 'int', 'group' => 'users', 'description' => 'Credits for new users'],
            ['key' => 'new_user_level', 'value' => 'USER', 'type' => 'string', 'group' => 'users', 'description' => 'Default level for new users'],
            ['key' => 'daily_time_limit', 'value' => '3600', 'type' => 'int', 'group' => 'users', 'description' => 'Daily time limit in seconds (default 1 hour)'],
            ['key' => 'max_time_bank', 'value' => '7200', 'type' => 'int', 'group' => 'users', 'description' => 'Maximum time bank in seconds'],
            ['key' => 'guest_access', 'value' => 'true', 'type' => 'bool', 'group' => 'users', 'description' => 'Allow guest login'],
            
            // Node settings
            ['key' => 'total_nodes', 'value' => '6', 'type' => 'int', 'group' => 'nodes', 'description' => 'Total number of nodes'],
            ['key' => 'node_timeout', 'value' => '900', 'type' => 'int', 'group' => 'nodes', 'description' => 'Node timeout in seconds (15 min)'],
            ['key' => 'bot_activity_enabled', 'value' => 'true', 'type' => 'bool', 'group' => 'nodes', 'description' => 'Enable simulated bot activity'],
            
            // File settings
            ['key' => 'max_file_size', 'value' => '10485760', 'type' => 'int', 'group' => 'files', 'description' => 'Max file size in bytes (10MB)'],
            ['key' => 'virus_scan_enabled', 'value' => 'true', 'type' => 'bool', 'group' => 'files', 'description' => 'Enable ClamAV virus scanning'],
            ['key' => 'file_approval_required', 'value' => 'true', 'type' => 'bool', 'group' => 'files', 'description' => 'Require SysOp approval for uploads'],
            ['key' => 'download_credit_cost', 'value' => '1', 'type' => 'int', 'group' => 'files', 'description' => 'Credits per KB downloaded'],
            ['key' => 'upload_credit_reward', 'value' => '2', 'type' => 'int', 'group' => 'files', 'description' => 'Credits per KB uploaded'],
            
            // AI settings
            ['key' => 'ai_stories_enabled', 'value' => 'true', 'type' => 'bool', 'group' => 'ai', 'description' => 'Enable AI story generation'],
            ['key' => 'ai_forum_bots_enabled', 'value' => 'true', 'type' => 'bool', 'group' => 'ai', 'description' => 'Enable AI forum bot posts'],
            ['key' => 'ai_oneliners_enabled', 'value' => 'true', 'type' => 'bool', 'group' => 'ai', 'description' => 'Enable AI oneliner generation'],
            ['key' => 'ai_model', 'value' => 'gpt-4', 'type' => 'string', 'group' => 'ai', 'description' => 'AI model to use'],
            ['key' => 'ai_story_style', 'value' => 'humorous', 'type' => 'string', 'group' => 'ai', 'description' => 'AI story style'],
            
            // Terminal settings
            ['key' => 'default_connection_speed', 'value' => 'scifi', 'type' => 'string', 'group' => 'terminal', 'description' => 'Default connection speed simulation'],
            ['key' => 'sound_effects_enabled', 'value' => 'true', 'type' => 'bool', 'group' => 'terminal', 'description' => 'Enable sound effects'],
            
            // Security
            ['key' => 'max_login_attempts', 'value' => '5', 'type' => 'int', 'group' => 'security', 'description' => 'Max failed login attempts before lockout'],
            ['key' => 'lockout_duration', 'value' => '900', 'type' => 'int', 'group' => 'security', 'description' => 'Lockout duration in seconds'],
            ['key' => 'ip_logging_enabled', 'value' => 'true', 'type' => 'bool', 'group' => 'security', 'description' => 'Enable IP logging'],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
