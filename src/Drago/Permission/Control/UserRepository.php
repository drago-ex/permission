<?php

declare(strict_types=1);

namespace Drago\Permission\Control;

use Dibi\Connection;
use Dibi\Exception;
use Drago\Attr\AttributeDetectionException;
use Drago\Attr\Table;
use Drago\Database\Database;


#[Table(UsersEntity::Table)]
class UserRepository
{
	use Database;

	public function __construct(
		private readonly Connection $connection,
	) {
	}


	/**
	 * @throws Exception
	 * @throws AttributeDetectionException
	 */
	public function getAllUsers(): array
	{
		return $this->read('*')
			->recordPairs(UsersEntity::PrimaryKey, UsersEntity::ColumnUsername);
	}
}
