<?php

declare(strict_types=1);

namespace LootRadar\Cli;

use LootRadar\Services\RadarService;

interface CliRadarFactoryInterface
{
    public function createFreeRadar(CliOptions $options): RadarService;

    public function createDealRadar(CliOptions $options, int $limit): RadarService;
}
