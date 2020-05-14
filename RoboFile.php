<?php

use Robo\ResultData;
use Robo\Tasks;
use Symfony\Component\Filesystem\Filesystem;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends Tasks {
  protected $sniffFilePatternsToExclude;
  protected $sniffArguments;
  protected $sniffTempFile;

  /**
   * Setup sniffer options.
   */
  public function __construct() {
    $this->sniffFilePatternsToExclude = '*.css,*.md,*.txt,*load.environment.php,*web/autoload.php,*scripts/composer/ScriptHandler.php,drush/Commands/PolicyCommands.php';
    $this->sniffTempFile = "/tmp/phpcs-fileset";
    $this->sniffArguments = "--standard=Drupal,DrupalPractice --colors -p --ignore='$this->sniffFilePatternsToExclude' -l";
  }

  /**
   * Setup project: configure the path to Drupal Codesniffer standards.
   */
  public function setupProject() {
    $this->taskExecStack()
      ->stopOnFail()
      ->exec('vendor/bin/phpcs --config-set installed_paths vendor/drupal/coder/coder_sniffer')->run();
    $this->installGitHooks();
  }

  /**
   * Install git hooks to check the commit message and coding standards.
   */
  public function installGitHooks() {
    $fs = new Filesystem();
    $path_to_hook_source = rtrim($fs->makePathRelative('scripts/git-hooks', '.git/hooks'), '/');
    $hooks = ['commit-msg', 'pre-commit'];
    foreach ($hooks as $hook) {
      $this->taskFilesystemStack()
        ->stopOnFail()
        ->symlink($path_to_hook_source . '/' . $hook, '.git/hooks/' . $hook)
        ->chmod('scripts/git-hooks/' . $hook, 0755)
        ->run();
    }
  }

  /**
   * Validate coding standards.
   */
  public function validateCodingStandards($file_list) {
    $this->say("Sniffing directories containing changed files...");
    $files = explode("\n", $file_list);
    $files = array_filter($files);
    if ($files) {
      $this->taskWriteToFile($this->sniffTempFile)
        ->lines($files)
        ->run();
      $exit_code = $this->performOperation('phpcs', $this->sniffArguments . " --file-list='$this->sniffTempFile'");
      unlink($this->sniffTempFile);
      if ($exit_code == ResultData::EXITCODE_ERROR || $exit_code == ResultData::EXITCODE_MISSING_OPTIONS) {
        $this->say('Validation failed, please try to run "robo fix:coding-standards" in order to try fix the code standards automatically');
      }
      return $exit_code;
    }
    return 0;
  }

  /**
   * Try to fix coding standards automatically.
   */
  public function fixCodingStandards() {
    $this->say("Trying to fix changed files...");
    $exit_code = $this->performOperation('phpcbf', $this->sniffArguments . ' ./');

    // - 0 indicates that no fixable errors were found.
    // - 1 indicates that all fixable errors were fixed correctly.
    // - 2 indicates that PHPCBF failed to fix some of the fixable errors.
    // - 3 is used for general script execution errors.
    switch ($exit_code) {
      case 0:
        $this->say('<info>No fixable errors were found, and so nothing was fixed.</info>');
        return 0;

      case 1:
        $this->say('<comment>Please note that exit code 1 does not indicate an error for PHPCBF.</comment>');
        $this->say('<info>All fixable errors were fixed correctly. There may still be errors that could not be fixed automatically.</info>');
        return 0;

      case 2:
        $this->logger->warning('PHPCBF failed to fix some of the fixable errors it found.');
    }
    return $exit_code;
  }

  /**
   * Helper method to launch phpcs or phpcbf.
   *
   * @param string $app
   *   Script to launch in vendor/bin path: "phpcs" or "phpcbf".
   * @param string $arguments
   *   Parameters to run phpcs.
   *
   * @return int
   *   Execution result code.
   *
   * @throws \Robo\Exception\TaskException
   */
  protected function performOperation($app, $arguments) {
    $bin = 'vendor/bin/' . $app;
    $command = "'$bin' $arguments";
    if ($this->output()->isVerbose()) {
      $command .= ' -v';
    }
    elseif ($this->output()->isVeryVerbose()) {
      $command .= ' -vv';
    }

    $result = $this->taskExecStack()
      ->exec($command)
      ->printMetadata(FALSE)
      ->run();

    return $result->getExitCode();
  }

  /**
   * Validates a git commit message.
   *
   * @aliases git:commit-msg
   *
   * @return int
   *   Execution result code.
   */
  public function commitMsgHook($message) {
    $this->say('Validating commit message syntax...');
    $project_prefix = 'DRPL';
    // Human readable help description explaining the pattern/restrictions.
    $help_description = "The commit message should include your project prefix,
    followed by a hyphen and ticket number, followed by a colon and a space,
    fifteen characters or more describing the commit, and end with a period.";
    // Provide an example of a valid commit message.
    $example = "$project_prefix-123: Update module configuration.";
    $pattern = "/(^$project_prefix-[0-9]+(: )[^ ].{15,}\\.)|(Merge branch (.)+)/";
    if (!preg_match($pattern, $message)) {
      $this->say("Invalid commit message! <comment>$message</comment>");
      $this->say("Commit messages must conform to the regex $pattern");
      if (!empty($help_description)) {
        $this->say("$help_description");
      }
      if (!empty($example)) {
        $this->say("Example: $example");
      }
      return 1;
    }
    return 0;
  }

}
