<?php

declare(strict_types=1);

namespace Cacti\Installer\Domain;

final readonly class InstallationPlan {
	/** @var non-empty-list<InstallTask> */
	private array $tasks;

	/** @param non-empty-list<InstallTask> $tasks */
	public function __construct(array $tasks) {
		$this->tasks = $tasks;
	}

	/** @return non-empty-list<InstallTask> */
	public function tasks(): array {
		return $this->tasks;
	}
}
