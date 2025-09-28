<?php

namespace App\Form;

use App\Entity\Language;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\LocaleType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType as SymfonyLanguageType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class LanguageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', LocaleType::class, [
                'label' => 'forms.language.code.label',
                'help' => 'forms.language.code.help',
                'translation_domain' => 'admin',
                'placeholder' => 'forms.language.code.placeholder',
                'preferred_choices' => ['fr', 'en', 'es', 'de', 'it', 'pt'],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Locale()
                ]
            ])
            ->add('name', SymfonyLanguageType::class, [
                'label' => 'forms.language.name.label',
                'help' => 'forms.language.name.help',
                'translation_domain' => 'admin',
                'placeholder' => 'forms.language.name.placeholder',
                'preferred_choices' => ['fr', 'en', 'es', 'de', 'it', 'pt'],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Language()
                ]
            ])
            ->add('nativeName', TextType::class, [
                'label' => 'forms.language.native_name.label',
                'help' => 'forms.language.native_name.help',
                'translation_domain' => 'admin',
                'attr' => [
                    'placeholder' => 'forms.language.native_name.placeholder',
                    'maxlength' => 100,
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['min' => 2, 'max' => 100])
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'forms.language.is_active.label',
                'help' => 'forms.language.is_active.help',
                'translation_domain' => 'admin',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ]
            ])
            ->add('isDefault', CheckboxType::class, [
                'label' => 'forms.language.is_default.label',
                'help' => 'forms.language.is_default.help',
                'translation_domain' => 'admin',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ]
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'forms.language.sort_order.label',
                'help' => 'forms.language.sort_order.help',
                'translation_domain' => 'admin',
                'attr' => [
                    'min' => 0,
                    'step' => 1,
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Range(['min' => 0, 'max' => 9999])
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Language::class,
        ]);
    }
}
