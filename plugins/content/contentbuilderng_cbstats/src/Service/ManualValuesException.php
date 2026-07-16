<?php

namespace CB\Plugin\Content\ContentbuilderngStats\Service;

\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

final class ManualValuesException extends \InvalidArgumentException
{
    public function __construct(private readonly string $entry)
    {
        parent::__construct('Invalid manual CBStats values.', 400);
    }

    public function getEntry(): string
    {
        return $this->entry;
    }
}
