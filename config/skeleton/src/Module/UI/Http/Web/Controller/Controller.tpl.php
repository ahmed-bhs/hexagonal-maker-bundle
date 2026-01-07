<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use <?= $use_case_namespace ?>\<?= $use_case_class ?>;
use <?= $command_namespace ?>\<?= $command_class ?>;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UI Layer - Web Controller
 *
 * Part of the UI (User Interface) layer in hexagonal architecture.
 * Responsible for:
 * - Handling HTTP requests
 * - Validating input
 * - Calling use cases/command handlers
 * - Rendering responses (templates, JSON, etc.)
 *
 * This controller is a PRIMARY ADAPTER (driving adapter) that drives the application core.
 */
#[Route('<?= $route_path ?>', name: '<?= $route_name ?>')]
final class <?= $class_name ?> extends AbstractController
{
    public function __construct(
        private readonly <?= $use_case_class ?> $useCase,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        // TODO: Implement controller logic
        // Example:
        //
        // 1. Create form (optional, for Web UI)
        // $form = $this->createForm(<?= $form_class ?>::class);
        // $form->handleRequest($request);
        //
        // if ($form->isSubmitted() && $form->isValid()) {
        //     $data = $form->getData();
        //
        //     // 2. Create command from form data
        //     $command = new <?= $command_class ?>(
        //         $data['field1'],
        //         $data['field2']
        //     );
        //
        //     try {
        //         // 3. Execute use case
        //         $response = $this->useCase->execute($command);
        //
        //         // 4. Add flash message and redirect
        //         $this->addFlash('success', 'Operation completed successfully');
        //         return $this->redirectToRoute('some_route');
        //
        //     } catch (\DomainException $e) {
        //         $this->addFlash('error', $e->getMessage());
        //     }
        // }
        //
        // return $this->render('<?= $template_path ?>', [
        //     'form' => $form->createView(),
        // ]);

        throw new \RuntimeException('Controller not implemented yet');
    }
}
