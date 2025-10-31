<?php

namespace Tourze\CATrustBundle\Verification;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum VerificationStatus: string implements Itemable, Labelable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case PASSED = '通过';
    case FAILED = '失败';
    case UNCERTAIN = '存疑';

    public function getLabel(): string
    {
        return $this->value;
    }
}
