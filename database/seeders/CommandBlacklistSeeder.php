<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CommandBlacklist;

class CommandBlacklistSeeder extends Seeder
{
    /**
     * Seed the command blacklist with default dangerous patterns.
     */
    public function run(): void
    {
        $entries = [
            // Filesystem destruction
            ['pattern' => 'rm -rf /', 'type' => 'contains', 'description' => 'Recursive force delete root filesystem'],
            ['pattern' => 'rm -rf /*', 'type' => 'contains', 'description' => 'Recursive force delete all root directories'],
            ['pattern' => 'rm -rf ~', 'type' => 'contains', 'description' => 'Recursive force delete home directory'],
            ['pattern' => 'rm -rf .', 'type' => 'contains', 'description' => 'Recursive force delete current directory'],
            ['pattern' => 'chmod 777', 'type' => 'contains', 'description' => 'Overly permissive file permissions'],
            ['pattern' => 'chmod -R 777', 'type' => 'contains', 'description' => 'Recursive overly permissive permissions'],

            // System control
            ['pattern' => 'shutdown', 'type' => 'exact', 'description' => 'System shutdown command'],
            ['pattern' => 'reboot', 'type' => 'exact', 'description' => 'System reboot command'],
            ['pattern' => 'halt', 'type' => 'exact', 'description' => 'System halt command'],
            ['pattern' => 'poweroff', 'type' => 'exact', 'description' => 'System poweroff command'],
            ['pattern' => 'init 0', 'type' => 'contains', 'description' => 'System halt via init'],
            ['pattern' => 'init 6', 'type' => 'contains', 'description' => 'System reboot via init'],

            // User management
            ['pattern' => 'passwd', 'type' => 'exact', 'description' => 'Password change command'],
            ['pattern' => 'useradd', 'type' => 'exact', 'description' => 'Add system user'],
            ['pattern' => 'userdel', 'type' => 'exact', 'description' => 'Delete system user'],
            ['pattern' => 'usermod', 'type' => 'exact', 'description' => 'Modify system user'],
            ['pattern' => 'groupadd', 'type' => 'exact', 'description' => 'Add system group'],
            ['pattern' => 'groupdel', 'type' => 'exact', 'description' => 'Delete system group'],

            // Disk operations
            ['pattern' => 'mkfs', 'type' => 'contains', 'description' => 'Format filesystem'],
            ['pattern' => 'fdisk', 'type' => 'contains', 'description' => 'Partition disk'],
            ['pattern' => 'dd if=', 'type' => 'contains', 'description' => 'Raw disk write'],
            ['pattern' => 'parted', 'type' => 'exact', 'description' => 'Partition editor'],

            // Network/firewall
            ['pattern' => 'iptables', 'type' => 'exact', 'description' => 'Firewall rules modification'],
            ['pattern' => 'ufw', 'type' => 'exact', 'description' => 'Firewall modification'],
            ['pattern' => 'nc -l', 'type' => 'contains', 'description' => 'Netcat listener (potential reverse shell)'],
            ['pattern' => 'ncat', 'type' => 'exact', 'description' => 'Ncat network tool'],

            // Process killing
            ['pattern' => 'kill -9 1', 'type' => 'contains', 'description' => 'Kill init process'],
            ['pattern' => 'killall', 'type' => 'exact', 'description' => 'Kill all processes by name'],
            ['pattern' => 'pkill', 'type' => 'exact', 'description' => 'Kill processes by pattern'],

            // Cron/scheduler
            ['pattern' => 'crontab', 'type' => 'exact', 'description' => 'Crontab manipulation'],

            // Sudo/privilege escalation
            ['pattern' => 'visudo', 'type' => 'contains', 'description' => 'Sudoers modification'],
            ['pattern' => 'sudoers', 'type' => 'contains', 'description' => 'Sudoers file reference'],
            ['pattern' => 'sudo su', 'type' => 'contains', 'description' => 'Switch to root user'],
            ['pattern' => 'sudo -i', 'type' => 'contains', 'description' => 'Root login shell'],

            // Service management
            ['pattern' => 'systemctl stop', 'type' => 'contains', 'description' => 'Stop system service'],
            ['pattern' => 'systemctl disable', 'type' => 'contains', 'description' => 'Disable system service'],
            ['pattern' => 'service stop', 'type' => 'contains', 'description' => 'Stop service (legacy)'],

            // Package management (destructive)
            ['pattern' => 'apt remove', 'type' => 'contains', 'description' => 'Remove system packages'],
            ['pattern' => 'apt purge', 'type' => 'contains', 'description' => 'Purge system packages'],
            ['pattern' => 'apt-get remove', 'type' => 'contains', 'description' => 'Remove system packages (legacy)'],
            ['pattern' => 'apt-get purge', 'type' => 'contains', 'description' => 'Purge system packages (legacy)'],
            ['pattern' => 'dpkg --purge', 'type' => 'contains', 'description' => 'Purge dpkg packages'],

            // Container/VM escape
            ['pattern' => 'docker', 'type' => 'exact', 'description' => 'Docker command access'],
            ['pattern' => 'chroot', 'type' => 'exact', 'description' => 'Change root directory'],
            ['pattern' => 'nsenter', 'type' => 'exact', 'description' => 'Namespace enter'],

            // SSH key manipulation
            ['pattern' => 'ssh-keygen', 'type' => 'exact', 'description' => 'SSH key generation'],
            ['pattern' => 'authorized_keys', 'type' => 'contains', 'description' => 'SSH authorized keys manipulation'],

            // Fork bomb patterns
            ['pattern' => ':(){ :|:& };:', 'type' => 'contains', 'description' => 'Fork bomb'],

            // Dangerous redirects
            ['pattern' => '> /dev/sda', 'type' => 'contains', 'description' => 'Write to raw disk device'],
            ['pattern' => '> /dev/null', 'type' => 'contains', 'description' => 'Redirect to null (data loss)'],
            ['pattern' => '/dev/zero', 'type' => 'contains', 'description' => 'Zero device reference'],
            ['pattern' => '/dev/random', 'type' => 'contains', 'description' => 'Random device reference'],
        ];

        foreach ($entries as $entry) {
            CommandBlacklist::firstOrCreate(
                ['pattern' => $entry['pattern'], 'type' => $entry['type']],
                array_merge($entry, ['is_active' => true])
            );
        }
    }
}
