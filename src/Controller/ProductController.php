<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Product;
use App\Entity\Category;
use App\Repository\ProductRepository;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;


class ProductController extends AbstractController
{


    //fonction pour afficher formulaire et ajouter un nouveau produit    
    #[Route('/product/add', name: 'product_add')]
    public function addNewProduct(EntityManagerInterface $entityManager, Request $request, SluggerInterface $slugger)
    {
        $product = new Product();


        $form = $this->createFormBuilder($product)
            ->add('nom', TextType::class)
            ->add('prix', TextType::class)
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'nom',
            ])
            ->add('description', TextareaType::class, [
                'attr' => ['class' => 'tinymce'],
            ])

            ->add('image', FileType::class, [
                'label' => 'Image',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '1024k',
                        'mimeTypes' => [
                            'image/gif',
                            'image/png',
                            'image/jpg',
                            'image/jpeg',
                        ],
                        'mimeTypesMessage' => 'Uniquement des fichiers de type : gif/png/jpg/jpeg',
                    ])
                ],
            ])
            ->add('save', SubmitType::class, ['label' => 'Ajouter'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $productName = $product->getNom();
            $existingProduct = $entityManager->getRepository(Product::class)->findOneBy(['nom' => $productName]);

            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move(
                        $this->getParameter('produit_image'),
                        $newFilename
                    );
                } catch (FileException $e) {
                }
                $product->setImage($newFilename);
            }

            if (!$existingProduct) {
                $entityManager->persist($product);
                $entityManager->flush();
                return $this->redirect('/', 201);
            } else {
                echo '<script>alert("Le produit existe déjà");</script>';
            }
        }
        return $this->render('product/add.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    //fonction pour afficher les produits 
    #[Route('/', name: 'home')]
    public function index(ProductRepository $productRepository): Response
    {

        $products = $productRepository->findAll();

        return $this->render('base.html.twig', [
            'products' => $products,
        ]);
    }

    //fonction pour afficher formulaire et ajouter une nouvelle categorie
    #[Route('/category/add', name: 'category_add')]
    public function addNewCategory(EntityManagerInterface $entityManager, Request $request)
    {
        $category = new Category();

        $form = $this->createFormBuilder($category)
            ->add('nom', TextType::class)
            ->add('submit', SubmitType::class, ['label' => 'Add Category'])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $categoryName = $category->getNom();
            $existingCategory = $entityManager->getRepository(Category::class)->findOneBy(['nom' => $categoryName]);
            if (!$existingCategory) {
                $entityManager->persist($category);
                $entityManager->flush();
                return $this->redirect('/', 201);
            } else {
                echo '<script>alert("La catégorie existe déjà");</script>';
            }
        }
        return $this->render('category/add.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    //fonction pour afficher les categories
    #[Route('/category', name: 'category')]
    public function showCategory(EntityManagerInterface $entityManager, Request $request)
    {
        $categories = $entityManager->getRepository(Category::class)
            ->findAll();

        if (!$categories) {
            throw $this->createNotFoundException(
                'No category found in category\'s table.'
            );
        } else {
            return $this->render('category/categories.html.twig', [
                'categories' => $categories,
            ]);
        }
    }


    //fonction pour afficher les details de chaque produit
    public function showProdDetails(EntityManagerInterface $entityManager, Request $request, int $id)
    {
        $product = $entityManager->getRepository(Product::class)
            ->find($id);

        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        return $this->render('product/details.html.twig', [
            'product' => $product,
        ]);
    }
}
