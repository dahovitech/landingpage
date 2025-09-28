<?php

namespace App\DataFixtures;

use App\Entity\Language;
use App\Entity\Service;
use App\Entity\ServiceTranslation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Create languages
        $french = new Language();
        $french->setCode('fr')
            ->setName('Français')
            ->setNativeName('Français')
            ->setIsActive(true)
            ->setIsDefault(true)
            ->setSortOrder(1);
        $manager->persist($french);

        $english = new Language();
        $english->setCode('en')
            ->setName('Anglais')
            ->setNativeName('English')
            ->setIsActive(true)
            ->setIsDefault(false)
            ->setSortOrder(2);
        $manager->persist($english);

        $spanish = new Language();
        $spanish->setCode('es')
            ->setName('Espagnol')
            ->setNativeName('Español')
            ->setIsActive(true)
            ->setIsDefault(false)
            ->setSortOrder(3);
        $manager->persist($spanish);

        $german = new Language();
        $german->setCode('de')
            ->setName('Allemand')
            ->setNativeName('Deutsch')
            ->setIsActive(true)
            ->setIsDefault(false)
            ->setSortOrder(4);
        $manager->persist($german);

        // Note: User creation is handled by UserFixtures.php

        // Flush languages first so we can reference them
        $manager->flush();

        // Create Services with translations
        $servicesData = [
            [
                'slug' => 'web-development',
                'translations' => [
                    'fr' => [
                        'title' => 'Développement Web',
                        'description' => 'Création de sites web modernes et responsifs avec les dernières technologies. Notre équipe développe des solutions web sur mesure adaptées à vos besoins spécifiques.',
                        'metaTitle' => 'Développement Web - Solutions digitales sur mesure',
                        'metaDescription' => 'Services de développement web professionnel. Création de sites web modernes, responsifs et optimisés pour votre entreprise.'
                    ],
                    'en' => [
                        'title' => 'Web Development',
                        'description' => 'Creation of modern and responsive websites with the latest technologies. Our team develops custom web solutions adapted to your specific needs.',
                        'metaTitle' => 'Web Development - Custom Digital Solutions',
                        'metaDescription' => 'Professional web development services. Creation of modern, responsive and optimized websites for your business.'
                    ],
                    'es' => [
                        'title' => 'Desarrollo Web',
                        'description' => 'Creación de sitios web modernos y responsivos con las últimas tecnologías. Nuestro equipo desarrolla soluciones web personalizadas adaptadas a sus necesidades específicas.',
                        'metaTitle' => 'Desarrollo Web - Soluciones Digitales Personalizadas',
                        'metaDescription' => 'Servicios profesionales de desarrollo web. Creación de sitios web modernos, responsivos y optimizados para su negocio.'
                    ],
                    'de' => [
                        'title' => 'Webentwicklung',
                        'description' => 'Erstellung moderner und responsiver Websites mit den neuesten Technologien. Unser Team entwickelt maßgeschneiderte Web-Lösungen, die an Ihre spezifischen Bedürfnisse angepasst sind.',
                        'metaTitle' => 'Webentwicklung - Maßgeschneiderte Digitale Lösungen',
                        'metaDescription' => 'Professionelle Webentwicklungsdienste. Erstellung moderner, responsiver und optimierter Websites für Ihr Unternehmen.'
                    ]
                ]
            ],
            [
                'slug' => 'mobile-app-development',
                'translations' => [
                    'fr' => [
                        'title' => 'Développement d\'Applications Mobiles',
                        'description' => 'Conception et développement d\'applications mobiles natives et cross-platform pour iOS et Android. Interface utilisateur intuitive et performance optimale.',
                        'metaTitle' => 'Développement d\'Apps Mobiles - iOS & Android',
                        'metaDescription' => 'Création d\'applications mobiles professionnelles pour iOS et Android. Solutions natives et cross-platform sur mesure.'
                    ],
                    'en' => [
                        'title' => 'Mobile App Development',
                        'description' => 'Design and development of native and cross-platform mobile applications for iOS and Android. Intuitive user interface and optimal performance.',
                        'metaTitle' => 'Mobile App Development - iOS & Android',
                        'metaDescription' => 'Professional mobile application development for iOS and Android. Custom native and cross-platform solutions.'
                    ],
                    'es' => [
                        'title' => 'Desarrollo de Aplicaciones Móviles',
                        'description' => 'Diseño y desarrollo de aplicaciones móviles nativas y multiplataforma para iOS y Android. Interfaz de usuario intuitiva y rendimiento óptimo.',
                        'metaTitle' => 'Desarrollo de Apps Móviles - iOS y Android',
                        'metaDescription' => 'Desarrollo profesional de aplicaciones móviles para iOS y Android. Soluciones nativas y multiplataforma personalizadas.'
                    ]
                ]
            ],
            [
                'slug' => 'digital-marketing',
                'translations' => [
                    'fr' => [
                        'title' => 'Marketing Digital',
                        'description' => 'Stratégies de marketing digital complètes incluant SEO, SEM, réseaux sociaux et marketing de contenu pour augmenter votre visibilité en ligne.',
                        'metaTitle' => 'Marketing Digital - SEO, SEM & Réseaux Sociaux',
                        'metaDescription' => 'Services de marketing digital complets. SEO, SEM, réseaux sociaux et stratégies de contenu pour votre croissance en ligne.'
                    ],
                    'en' => [
                        'title' => 'Digital Marketing',
                        'description' => 'Comprehensive digital marketing strategies including SEO, SEM, social media and content marketing to increase your online visibility.',
                        'metaTitle' => 'Digital Marketing - SEO, SEM & Social Media',
                        'metaDescription' => 'Comprehensive digital marketing services. SEO, SEM, social media and content strategies for your online growth.'
                    ]
                ]
            ],
            [
                'slug' => 'e-commerce-solutions',
                'translations' => [
                    'fr' => [
                        'title' => 'Solutions E-commerce',
                        'description' => 'Développement de boutiques en ligne performantes avec système de paiement sécurisé, gestion des stocks et tableau de bord administrateur.',
                        'metaTitle' => 'Solutions E-commerce - Boutiques en ligne',
                        'metaDescription' => 'Développement de boutiques en ligne professionnelles. Paiement sécurisé, gestion des stocks et interface d\'administration.'
                    ],
                    'en' => [
                        'title' => 'E-commerce Solutions',
                        'description' => 'Development of high-performance online stores with secure payment system, inventory management and admin dashboard.',
                        'metaTitle' => 'E-commerce Solutions - Online Stores',
                        'metaDescription' => 'Professional online store development. Secure payment, inventory management and admin interface.'
                    ]
                ]
            ]
        ];

        foreach ($servicesData as $index => $serviceData) {
            $service = new Service();
            $service->setSlug($serviceData['slug']);
            $service->setIsActive(true);
            $service->setSortOrder($index + 1);
            
            $manager->persist($service);

            // Add translations
            foreach ($serviceData['translations'] as $langCode => $translationData) {
                $language = null;
                switch ($langCode) {
                    case 'fr': $language = $french; break;
                    case 'en': $language = $english; break;
                    case 'es': $language = $spanish; break;
                    case 'de': $language = $german; break;
                }

                if ($language) {
                    $translation = new ServiceTranslation();
                    $translation->setService($service);
                    $translation->setLanguage($language);
                    $translation->setTitle($translationData['title']);
                    $translation->setDescription($translationData['description']);
                    $translation->setMetaTitle($translationData['metaTitle'] ?? null);
                    $translation->setMetaDescription($translationData['metaDescription'] ?? null);
                    
                    $manager->persist($translation);
                    $service->addTranslation($translation);
                }
            }
        }

        $manager->flush();
    }
}
