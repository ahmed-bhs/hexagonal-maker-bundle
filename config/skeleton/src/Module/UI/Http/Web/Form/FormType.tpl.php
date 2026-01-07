<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * UI Layer - Symfony Form Type
 *
 * Defines the structure and validation of a web form.
 * Part of the UI layer in hexagonal architecture.
 *
 * Used by controllers to:
 * - Render HTML forms
 * - Validate user input
 * - Map form data to DTOs/Commands
 */
final class <?= $class_name ?> extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // TODO: Add your form fields here
            // Example:
            // ->add('title', TextType::class, [
            //     'label' => 'Title',
            //     'required' => true,
            //     'attr' => ['class' => 'form-control'],
            // ])
            // ->add('content', TextareaType::class, [
            //     'label' => 'Content',
            //     'required' => true,
            //     'attr' => ['class' => 'form-control', 'rows' => 10],
            // ])
            // ->add('email', EmailType::class, [
            //     'label' => 'Email',
            //     'required' => true,
            // ])
            // ->add('publishedAt', DateTimeType::class, [
            //     'label' => 'Published At',
            //     'required' => false,
            //     'widget' => 'single_text',
            // ])
            // ->add('isActive', CheckboxType::class, [
            //     'label' => 'Active',
            //     'required' => false,
            // ])
            ->add('save', SubmitType::class, [
                'label' => 'Save',
                'attr' => ['class' => 'btn btn-primary'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Uncomment to map form to a DTO class:
            // 'data_class' => <?= $input_class ?>::class,

            'attr' => [
                'class' => 'needs-validation',
                'novalidate' => true,
            ],
        ]);
    }
}
