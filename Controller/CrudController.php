<?php

namespace AppBundle\Controller;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\Entity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class CrudController extends Controller
{

	/**
	 * @return array
	 */
	abstract function getTableFields();

	/**
	 * @return array
	 */
	abstract function getShowFields();


	/**
	 * @return object
	 */
	abstract function getNewEntity();

	/**
	 * @return FormType
	 */
	abstract function getEntityForm();

	/**
	 * @return EntityRepository
	 */
	abstract function getRepository();

	private function getFieldsMetadata($fields){
		$fieldsArray = [];
		foreach ($fields as $field){
			$type = $this->getEntityManager()->getClassMetadata(get_class($this->getNewEntity()))->getTypeOfField($field);
			$fieldsArray[] =["name"=>$field, "type"=>$type];
		}
		return $fieldsArray;
	}

	/**
	 * Takes the entity metadata introspected via Doctrine and completes its
	 * contents to simplify data processing for the rest of the application.
	 *
	 * @param ClassMetadata $entityMetadata The entity metadata introspected via Doctrine
	 *
	 * @return array The entity properties metadata provided by Doctrine
	 */
	private function processEntityPropertiesMetadata(ClassMetadata $entityMetadata)
	{
		$entityPropertiesMetadata = array();

		// introspect regular entity fields
		foreach ($entityMetadata->fieldMappings as $fieldName => $fieldMetadata) {
			$entityPropertiesMetadata[$fieldName] = $fieldMetadata;
		}

		// introspect fields for entity associations
		foreach ($entityMetadata->associationMappings as $fieldName => $associationMetadata) {
			$entityPropertiesMetadata[$fieldName] = array_merge($associationMetadata, array(
				'type' => 'association',
				'associationType' => $associationMetadata['type'],
			));

			// associations different from *-to-one cannot be sorted
			if ($associationMetadata['type'] & ClassMetadata::TO_MANY) {
				$entityPropertiesMetadata[$fieldName]['sortable'] = false;
			}
		}

		return $entityPropertiesMetadata;
	}
	/**
	 * @return string
	 */
	public function getBaseTwigs(){
		return "@App/default";
	}

	public function prePersistNew($entity, Request $request){
		//todo: Implementar este metodo para hacer algo con la entidad antes de persistirla en base de datos
	}

	public function postPersistNew($entity, Request $request){
		//todo: Implementar este metodo para hacer algo con la entidad después de persistirla en base de datos
	}

	public function prePersistEdit($entity, Request $request){
		//todo: Implementar este metodo para hacer algo con la entidad antes de persistirla en base de datos
	}

	public function postPersistEdit($entity, Request $request){
		//todo: Implementar este metodo para hacer algo con la entidad después de persistirla en base de datos
	}

	/**
	 * @return \Doctrine\Common\Persistence\ObjectManager|object
	 */
	private function getEntityManager(){
		return $this->getDoctrine()->getManager();
	}

	private function getEntityName(){
		return strtolower((new \ReflectionClass($this->getNewEntity()))->getShortName());
	}

	/**
	 * Lists all entities.
	 */
	public function indexAction()
	{
		$entities = $this->getRepository()->findAll();

		return $this->render($this->getBaseTwigs().'/index.html.twig', array(
			'entities' => $entities,
			'entityName' => $this->getEntityName(),
			'fields' => $this->getFieldsMetadata($this->getTableFields())
			));
	}


	public function newAction(Request $request)
	{
		$entity = $this->getNewEntity();
		$form = $this->createForm($this->getEntityForm(), $entity, ["action"=>$this->generateUrl($this->getEntityName().'_new'), "method"=>"post"]);
		$form->handleRequest($request);
		$translator = $this->get('translator');

		if(!$request->request->get("rebuild")) {
			if ( $form->isSubmitted() ) {
				if ( $form->isValid() ) {
					$em = $this->getDoctrine()->getManager();
					$this->prePersistNew($entity, $request);
					$em->persist( $entity );
					$em->flush();
					$this->postPersistNew($entity, $request);
					return new JsonResponse( array( 'response' => 'success', 'message' => ucfirst($translator->trans($this->getEntityName())). $translator->trans('creado') ) );
				} else {
					$errors = $this->get( 'app.form_serializer' )->serializeFormErrors( $form, true, true );
					return new JsonResponse( array( 'response' => 'error', 'errors' => $errors ) );
				}

			}
		}

		return $this->render($this->getBaseTwigs().'/new.html.twig', array(
			'entity' => $entity,
			'entityName' => $this->getEntityName(),
			'form' => $form->createView(),
		));
	}

	/**
	 * Finds and displays an entity.
	 * @param $id integer
	 * @return Response
	 */
	public function showAction($id)
	{
		$entity = $this->getRepository()->find($id);
		return $this->render($this->getBaseTwigs().'/show.html.twig', array(
			'entity' => $entity,
			'entityName' => $this->getEntityName(),
			'fields' => $this->getFieldsMetadata($this->getShowFields())
		));
	}

	/**
	 * Displays a form to edit an existing entity.
	 * @param $id integer
	 * @return Response
	 */
	public function editAction(Request $request, $id)
	{
		$entity = $this->getRepository()->find($id);
		$editForm = $this->createForm($this->getEntityForm(), $entity, ["action"=>$this->generateUrl($this->getEntityName().'_edit', ["id"=>$entity->getId()]), "method"=>"post"]);
		$editForm->handleRequest($request);

		if(!$request->request->get("rebuild")) {

			if ( $editForm->isSubmitted() ) {
				if ( $editForm->isValid() ) {
					$this->prePersistEdit($entity,$request);
					$this->getDoctrine()->getManager()->flush();
					$this->postPersistEdit($entity,$request);
					$translator = $this->get('translator');
					return new JsonResponse( array( 'response' => 'success', 'message' => ucfirst($translator->trans($this->getEntityName())). $translator->trans('actualizado') ) );
				} else {
					$errors = $this->get( 'app.form_serializer' )->serializeFormErrors( $editForm, true, true );

					return new JsonResponse( array( 'response' => 'error', 'errors' => $errors ) );
				}
			}
		}

		return $this->render($this->getBaseTwigs().'/edit.html.twig', array(
			'entity' => $entity,
			'entityName' => $this->getEntityName(),
			'edit_form' => $editForm->createView()
		));
	}

	/**
	 * @param $id integer
	 * @return Response
	 */
	public function deleteAction(Request $request, $id)
	{
		$entity = $this->getRepository()->find($id);
		$em = $this->getDoctrine()->getManager();
		$em->remove($entity);
		$em->flush();
		$translator = $this->get('translator');
		return new JsonResponse(array('response' => 'success', 'message' =>  ucfirst($translator->trans($this->getEntityName())). $translator->trans('eliminado') ));
	}

}
