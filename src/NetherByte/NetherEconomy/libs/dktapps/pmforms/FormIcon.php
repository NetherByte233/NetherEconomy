<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace NetherByte\NetherEconomy\libs\dktapps\pmforms;

/**
 * Represents an icon which can be placed next to options on menus, or as the icon for the server-settings form type.
 */
class FormIcon implements \JsonSerializable{
	public const IMAGE_TYPE_URL = "url";
	public const IMAGE_TYPE_PATH = "path";

	/** @var string */
	private $type;
	/** @var string */
	private $data;

	/**
	 * @param string $data URL or path depending on the type chosen.
	 * @param string $type Can be one of the constants at the top of the file, but only "url" is known to work.
	 */
	public function __construct(string $data, string $type = self::IMAGE_TYPE_URL){
		$this->type = $type;
		$this->data = $data;
	}

	public function getType() : string{
		return $this->type;
	}

	public function getData() : string{
		return $this->data;
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize(){
		return [
			"type" => $this->type,
			"data" => $this->data
		];
	}
}