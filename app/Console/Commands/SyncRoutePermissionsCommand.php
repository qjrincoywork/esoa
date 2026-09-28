<?php

namespace App\Console\Commands;

use App\Services\RoutePermissionService;
use Illuminate\Console\Command;

class SyncRoutePermissionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:sync {--dry-run : List what would be created without writing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register a permission for every named route guarded by check_permissions that does not have one yet. Also runs automatically after `migrate`.';

    /**
     * Create (or, with --dry-run, list) the missing route permissions, then list the
     * permissions no route is named after so they can be reviewed by hand.
     */
    public function handle(RoutePermissionService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $names = $dryRun ? $service->missing() : $service->sync();

        if ($names->isEmpty()) {
            $this->info('Every guarded route already has a permission.');
        } else {
            $this->info(($dryRun ? 'Would create' : 'Created') . " {$names->count()} permission(s):");
            $names->each(fn (string $name) => $this->line("  + {$name}"));

            if (!$dryRun) {
                $this->comment('New permissions are ungranted; assign them through Roles or Manage Permissions.');
            }
        }

        $orphans = $service->orphans();

        if ($orphans->isNotEmpty()) {
            $this->newLine();
            $this->warn("{$orphans->count()} permission(s) match no guarded route (left untouched — review before removing):");
            $orphans->each(fn (string $name) => $this->line("  ? {$name}"));
        }

        return self::SUCCESS;
    }
}
