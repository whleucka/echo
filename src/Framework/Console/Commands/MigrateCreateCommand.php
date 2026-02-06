<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'migrate:create', description: 'Create a new migration file')]
class MigrateCreateCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('table', InputArgument::REQUIRED, 'Table name for the migration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = $input->getArgument('table');
        $migrationPath = config("paths.migrations");
        $time = time();
        $fileName = sprintf("%s_create_%s.php", $time, $table);
        $filePath = sprintf("%s/%s", $migrationPath, $fileName);

        $template = <<<EOT
<?php

use Echo\Interface\Database\Migration;
use Echo\Framework\Database\{Schema, Blueprint};

return new class implements Migration
{
    private string \$table = "$table";

    public function up(): string
    {
         return Schema::create(\$this->table, function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
            \$table->primaryKey("id");
        });
    }

    public function down(): string
    {
         return Schema::drop(\$this->table);
    }
};
EOT;

        $result = file_put_contents($filePath, $template);
        if ($result) {
            $output->writeln("<info>✓ Successfully created $fileName</info>");
            return Command::SUCCESS;
        }

        $output->writeln("<error>Failed to create migration file</error>");
        return Command::FAILURE;
    }
}
