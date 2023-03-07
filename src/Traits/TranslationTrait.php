<?php

namespace AmazonPayCheckout\Traits;

use Plenty\Plugin\Translation\Translator;

trait TranslationTrait
{

    /**
     * @var Translator
     */
    protected $translator;

    public function getTranslation(string $variable)
    {
        if (empty($this->translator)) {
            $this->translator = pluginApp(Translator::class);
        }
        return $this->translator->trans('AmazonPayCheckout::' . $variable);
    }
}