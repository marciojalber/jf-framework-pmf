<?php

namespace JF\HTML\Elements\Table;

use JF\HTML\Elements\Element_Trait;

/**
 * Cria uma nova <td>.
 */
class TD
{
    use Element_Trait, TH_TD_Trait {
        TH_TD_Trait::mount insteadof Element_Trait;
    }

    /**
     * Nome da TAG.
     */
    protected static $tag = 'td';
}
