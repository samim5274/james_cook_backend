<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class DatabaseBackupMail extends Command
{

    protected $signature = 'backup:mail';
    protected $description = 'DB backup zip with password and send mail';

    public function handle()
    {
        $db = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');

        $zipPassword = env('ZIP_PASSWORD', 'Sh@mim4746');

        $time = date('Y-m-d_H-i-s');

        $sqlFile = "backup-{$time}.sql";
        $zipFile = "backup-{$time}.zip";

        $sqlPath = storage_path("app/{$sqlFile}");
        $zipPath = storage_path("app/{$zipFile}");

        try {

            // 1. DB BACKUP
            $dump = sprintf(
                "MYSQL_PWD=%s mysqldump --single-transaction --quick --lock-tables=false --host=%s --user=%s %s > %s 2>&1",
                escapeshellarg($pass),
                escapeshellarg('127.0.0.1'),
                escapeshellarg($user),
                escapeshellarg($db),
                escapeshellarg($sqlPath)
            );

            exec($dump, $output, $return);

            if ($return !== 0 || !file_exists($sqlPath) || filesize($sqlPath) < 100) {
                throw new \Exception("Database backup failed");
            }

            // 2. ZIP (password protected)
            $zip = sprintf(
                "zip -j -P %s %s %s",
                escapeshellarg($zipPassword),
                escapeshellarg($zipPath),
                escapeshellarg($sqlPath)
            );

            exec($zip, $zout, $zreturn);

            if (file_exists($sqlPath)) {
                unlink($sqlPath);
            }

            if ($zreturn !== 0 || !file_exists($zipPath)) {
                throw new \Exception("ZIP creation failed");
            }

            // 3. EMAIL SEND
            Mail::raw(
                "Your database backup is ready.\n\nPassword: ******** ",
                function ($message) use ($zipPath, $zipFile) {
                    $message->to('valobashi.tumake9999@gmail.com')
                        ->subject('Database Backup')
                        ->attach($zipPath, [
                            'as' => $zipFile,
                            'mime' => 'application/zip'
                        ]);
                }
            );

            $this->info("Backup sent successfully!");

            // optional cleanup
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }

        } catch (\Throwable $e) {
            $this->error("Backup failed: " . $e->getMessage());
        }
    }
}
