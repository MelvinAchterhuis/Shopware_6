<?php

namespace Buckaroo\Shopware6\Buckaroo\Payload;

use Buckaroo\Shopware6\Buckaroo\Payload\TransactionRequest;
use Buckaroo\Shopware6\Helpers\Helpers;

/**
 * DataRequest inherits from TransactionRequest
 * All differences between the two are fixed here
 */
class DataRequest extends TransactionRequest
{
	/**
	 * Set an additional parameter
	 * Structure is AdditionalParameters -> List
	 * 
	 * @param string $key
	 * @param string $value
	 * @return string $value
	 */
	public function setAdditionalParameter($key, $value)
	{
		if( !isset($this->data['AdditionalParameters']) ) $this->data['AdditionalParameters'] = [];
		if( !isset($this->data['AdditionalParameters']['List']) ) $this->data['AdditionalParameters']['List'] = [];

		foreach ($this->data['AdditionalParameters']['List'] as $i => $additional)
		{
			$name = $additional['Name'];

			if( $name === $key )
			{
				$this->data['AdditionalParameters']['List'][$i]['Value'] = $value;
				return $value;
			}
		}

		$this->data['AdditionalParameters']['List'][] = [
			'Name' => $key,
			'Value' => $value
		];

		return $value;
	}
}
