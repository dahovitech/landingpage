<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MediaTextareaType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        
        $view->vars['enable_media'] = $options['enable_media'];
        $view->vars['enable_editor'] = $options['enable_editor'];
        $view->vars['editor_height'] = $options['editor_height'];
        
        // Ajouter les classes CSS nécessaires
        $classes = $view->vars['attr']['class'] ?? '';
        if ($options['enable_editor']) {
            $classes .= ' custom-editor';
        }
        if ($options['enable_media']) {
            $view->vars['attr']['data-enable-media'] = 'true';
        }
        $view->vars['attr']['class'] = trim($classes);
        $view->vars['attr']['data-editor-height'] = $options['editor_height'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'enable_media' => true,
            'enable_editor' => true,
            'editor_height' => 300,
        ]);

        $resolver->setAllowedTypes('enable_media', 'bool');
        $resolver->setAllowedTypes('enable_editor', 'bool');
        $resolver->setAllowedTypes('editor_height', 'int');
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'media_textarea';
    }
}
