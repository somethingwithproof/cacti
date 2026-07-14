<?php

declare(strict_types=1);

namespace Cacti\Installer\Application;

final readonly class InstallationExecutionResult {
	private function __construct(
		public bool $successful,
		public ?string $failure = null,
	) {
	}

	public static function completed(): self {
		return new self(true);
	}

	public static function failed(string $failure): self {
		return new self(false, $failure);
	}
}
