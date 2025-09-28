<?php

namespace App\Form\Type;

use App\Entity\Media;
use App\Repository\MediaRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MediaSelectorType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        
        $view->vars['multiple'] = $options['multiple'];
        $view->vars['show_preview'] = $options['show_preview'];
        $view->vars['allow_upload'] = $options['allow_upload'];
        
        // Ajouter les classes CSS et attributs data nécessaires
        $view->vars['attr']['class'] = ($view->vars['attr']['class'] ?? '') . ' media-selector';
        $view->vars['attr']['data-multiple'] = $options['multiple'] ? 'true' : 'false';
        $view->vars['attr']['data-show-preview'] = $options['show_preview'] ? 'true' : 'false';
        $view->vars['attr']['data-allow-upload'] = $options['allow_upload'] ? 'true' : 'false';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Media::class,
            'choice_label' => function(Media $media) {
                return $media->getAlt() ?: $media->getFileName();
            },
            'choice_value' => 'id',
            'multiple' => true,
            'expanded' => false,
            'show_preview' => true,
            'allow_upload' => true,
            'query_builder' => function (MediaRepository $repository) {
                return $repository->createQueryBuilder('m')
                    ->orderBy('m.id', 'DESC');
            },
        ]);

        $resolver->setAllowedTypes('show_preview', 'bool');
        $resolver->setAllowedTypes('allow_upload', 'bool');
    }

    public function getParent(): string
    {
        return EntityType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'media_selector';
    }
}
