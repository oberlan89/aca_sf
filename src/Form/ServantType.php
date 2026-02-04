<?php

namespace App\Form;

use App\Entity\Servant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName')
            ->add('lastName1')
            ->add('lastName2')
            ->add('gender')
            ->add('email')
            ->add('birthMonth')
            ->add('birthDay')
            ->add('key')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Servant::class,
        ]);
    }
}
