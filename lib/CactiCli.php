<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleException;

/**
 * Thin Cacti-facing wrapper over Symfony Console's input parser.
 *
 * Cacti's cli/ scripts each hand-roll the same `foreach ($argv)` loop with an
 * `explode('=', ...)` split, a bespoke display_help(), and their own --version
 * handling. This declares the options once and gets parsing, validation of
 * unknown options, --help, and --version for free, while preserving Cacti's
 * established `--name=value` and `--flag` conventions and its help layout.
 *
 * The Symfony pieces stay behind this class so scripts depend on Cacti, not on
 * Console's Command/Application ceremony, which does not fit the flat-script
 * model.
 */
class CactiCli {
	private InputDefinition $definition;

	/** @var array<string, string> option name => one-line description */
	private array $descriptions = [];

	private ArgvInput|null $input = null;

	public function __construct(
		private readonly string $script,
		private readonly string $summary = ''
	) {
		$this->definition = new InputDefinition();

		$this->definition->addOption(new InputOption('help', 'h', InputOption::VALUE_NONE, 'Display this help and exit'));
		$this->definition->addOption(new InputOption('version', 'V', InputOption::VALUE_NONE, 'Display the Cacti version and exit'));
	}

	/**
	 * Declare a value option consumed as --name=value.
	 */
	public function option(string $name, string $description, bool $required = false, string|null $default = null) : self {
		$mode = $required ? InputOption::VALUE_REQUIRED : InputOption::VALUE_OPTIONAL;

		$this->definition->addOption(new InputOption($name, null, $mode, $description, $required ? null : $default));
		$this->descriptions[$name] = $description;

		return $this;
	}

	/**
	 * Declare a boolean flag consumed as --name.
	 */
	public function flag(string $name, string $description) : self {
		$this->definition->addOption(new InputOption($name, null, InputOption::VALUE_NONE, $description));
		$this->descriptions[$name] = $description;

		return $this;
	}

	/**
	 * Parse $argv. Handles --help and --version by printing and exiting, and
	 * exits non-zero with the usage on an unknown or malformed option so a
	 * script never proceeds on unparsed input.
	 */
	public function parse(array|null $argv = null) : self {
		$argv ??= $_SERVER['argv'];

		// ArgvInput expects the script name in slot 0, which it discards.
		// Bind the definition inside the try below so a bad option routes
		// through the usage handler rather than throwing from the constructor.
		$input = new ArgvInput($argv);

		if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
			print $this->help();
			exit(0);
		}

		if (in_array('--version', $argv, true) || in_array('-V', $argv, true)) {
			print $this->script . ' ' . (defined('CACTI_VERSION') ? CACTI_VERSION : 'unknown') . "\n";
			exit(0);
		}

		try {
			$input->bind($this->definition);
			$input->validate();
		} catch (ConsoleException $e) {
			fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n\n");
			fwrite(STDERR, $this->help());
			exit(1);
		}

		$this->input = $input;

		return $this;
	}

	/**
	 * Value of a declared option, or its default when absent.
	 */
	public function get(string $name) : mixed {
		return $this->requireParsed()->getOption($name);
	}

	/**
	 * True when a flag was supplied.
	 */
	public function isset(string $name) : bool {
		return (bool) $this->requireParsed()->getOption($name);
	}

	/**
	 * Render the --help text in Cacti's conventional layout.
	 */
	public function help() : string {
		$out = $this->script . ($this->summary !== '' ? ' - ' . $this->summary : '') . "\n\n";
		$out .= 'usage: ' . $this->script . " [--option=value] [--flag]\n\n";

		$width = 0;

		foreach (array_keys($this->descriptions) as $name) {
			$width = max($width, strlen($name));
		}

		foreach ($this->descriptions as $name => $description) {
			$out .= '    --' . str_pad($name, $width) . '    ' . $description . "\n";
		}

		$out .= "\n    --" . str_pad('version', $width) . "    Display the Cacti version and exit\n";
		$out .= '    --' . str_pad('help', $width) . "    Display this help and exit\n";

		return $out;
	}

	private function requireParsed() : ArgvInput {
		if ($this->input === null) {
			throw new RuntimeException('CactiCli::parse() must run before reading options');
		}

		return $this->input;
	}
}
