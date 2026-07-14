<?php

declare(strict_types=1);

namespace Cacti\Installer\Infrastructure;

final readonly class PlatformDetector {
	public function detect(): OperatingSystem {
		return OperatingSystem::fromPhpOsFamily(PHP_OS_FAMILY);
	}
}
