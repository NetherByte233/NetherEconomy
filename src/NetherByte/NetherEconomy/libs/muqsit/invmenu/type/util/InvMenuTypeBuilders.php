<?php

declare(strict_types=1);

namespace NetherByte\NetherEconomy\libs\muqsit\invmenu\type\util;

use NetherByte\NetherEconomy\libs\muqsit\invmenu\type\util\builder\ActorFixedInvMenuTypeBuilder;
use NetherByte\NetherEconomy\libs\muqsit\invmenu\type\util\builder\BlockActorFixedInvMenuTypeBuilder;
use NetherByte\NetherEconomy\libs\muqsit\invmenu\type\util\builder\BlockFixedInvMenuTypeBuilder;
use NetherByte\NetherEconomy\libs\muqsit\invmenu\type\util\builder\DoublePairableBlockActorFixedInvMenuTypeBuilder;

final class InvMenuTypeBuilders{

	public static function ACTOR_FIXED() : ActorFixedInvMenuTypeBuilder{
		return new ActorFixedInvMenuTypeBuilder();
	}

	public static function BLOCK_ACTOR_FIXED() : BlockActorFixedInvMenuTypeBuilder{
		return new BlockActorFixedInvMenuTypeBuilder();
	}

	public static function BLOCK_FIXED() : BlockFixedInvMenuTypeBuilder{
		return new BlockFixedInvMenuTypeBuilder();
	}

	public static function DOUBLE_PAIRABLE_BLOCK_ACTOR_FIXED() : DoublePairableBlockActorFixedInvMenuTypeBuilder{
		return new DoublePairableBlockActorFixedInvMenuTypeBuilder();
	}
}