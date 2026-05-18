<?php

declare(strict_types=1);

namespace Tests\Application\Actions;

use App\Application\Actions\ActionError;
use Tests\TestCase;

class ActionErrorTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $error = new ActionError(ActionError::BAD_REQUEST, 'Invalid input');

        $this->assertSame(ActionError::BAD_REQUEST, $error->getType());
        $this->assertSame('Invalid input', $error->getDescription());
    }

    public function testDescriptionDefaultsToNull(): void
    {
        $error = new ActionError(ActionError::SERVER_ERROR);

        $this->assertNull($error->getDescription());
    }

    public function testSettersAreFluentAndUpdateValues(): void
    {
        $error = new ActionError(ActionError::SERVER_ERROR);

        $result = $error
            ->setType(ActionError::VALIDATION_ERROR)
            ->setDescription('Bad field');

        $this->assertSame($error, $result);
        $this->assertSame(ActionError::VALIDATION_ERROR, $error->getType());
        $this->assertSame('Bad field', $error->getDescription());
    }

    public function testSetDescriptionAcceptsNull(): void
    {
        $error = new ActionError(ActionError::SERVER_ERROR, 'oops');

        $error->setDescription();

        $this->assertNull($error->getDescription());
    }

    public function testJsonSerialize(): void
    {
        $error = new ActionError(ActionError::NOT_ALLOWED, 'nope');

        $this->assertSame(
            ['type' => ActionError::NOT_ALLOWED, 'description' => 'nope'],
            $error->jsonSerialize()
        );
    }
}
