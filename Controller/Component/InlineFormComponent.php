<?php
	class InlineFormComponent extends Component {
		public function inlineform($ctr) {
			$ctr->layout = 'default';
			$request = $ctr->data;
			$action = Hash::get($request, 'action');

			$path = explode('.', Hash::get($request, 'path'));

			if (count($path) < 1) throw new InternalErrorException('Invalid Path');

			$modelName = $path[0];

			$model = $ctr->{$modelName};
			$defaultModelName = Inflector::singularize($ctr->name);
			$defaultModel = $ctr->{$defaultModelName};
			if ($model == null) {
				$model = $defaultModel->{$modelName};
			}

			if ($model == null) throw new InternalErrorException('Model Not Found');

			$id = Hash::get($request, 'id');

			if ($action == 'update') {
				if (count($path) != 2) throw new InternalErrorException('Invalid Path');
				$field = $path[1];
				if (!$model->getColumnType($field)) throw new InternalErrorException('Invalid Path');
				$value = Hash::get($request, 'value');

				if ($id !== null) {
					$model->id = $id;
					if (!$model->exists()) throw new InternalErrorException('Invalid ID');
					if (method_exists($model, 'isOwnedBy') && !$model->isOwnedBy($id, $ctr->Auth->user('id'))) throw new ForbiddenException('Permission Denied');

					$model->read();
				} else {
					if ($field != Inflector::underscore($defaultModel->name) . '_id') throw new InternalErrorException;
					if (method_exists($model, 'isOwnedBy') && !$model->isOwnedBy($id, $ctr->Auth->user('id'))) throw new ForbiddenException('Permission Denied');
					$model->create();
					if ($model->getColumnType('user_id')) $model->set('user_id', $this->Auth->user('id'));
				}

				$model->set($field, $value);

				if (!$model->save()) throw new InternalErrorException('Save Failed');

				if ($model != $defaultModel) {
					$refField = Inflector::underscore($defaultModel->name) . '_id';
					$defaultId = is_array($model->data) ? Hash::get($model->data, $model->name . '.' . $refField) : null;
					if ($defaultId == null) $defaultId = $model->field($refField);
				} else {
					$defaultId = $defaultModel->id;
				}

				$result = ['status' => 'OK', 'data' => $defaultModel->find('first', ['conditions' => [($defaultModel->name . '.id') => $defaultId]])];

				$ctr->set('data', $result);
				$ctr->set('_serialize', 'data');
			} else if ($action == 'delete') {
				if ($ctr->request->is('get')) {
					throw new MethodNotAllowedException();
				}

				$model->id = $id;
				if (!$model->exists()) throw new InternalErrorException('Invalid ID');
				if (method_exists($model, 'isOwnedBy') && !$model->isOwnedBy($id, $ctr->Auth->user('id'))) throw new ForbiddenException('Permission Denied');

				if ($model != $defaultModel) {
					$refField = Inflector::underscore($defaultModel->name) . '_id';
					$defaultId = is_array($model->data) ? Hash::get($model->data, $model->name . '.' . $refField) : null;
					if ($defaultId == null) $defaultId = $model->field($refField);
				} else {
					$defaultId = $defaultModel->id;
				}

				if (!$model->delete($id)) throw new InternalErrorException('Delete Failed');

				$ctr->set('data', [
						'status' => 'OK',
						'id' => $id,
						'model' => $modelName,
						'data' => $defaultModel->find('first', [
								'conditions' => [
									($defaultModel->name . '.id') => $defaultId
								]
							]
						)
					]);
				$ctr->set('_serialize', 'data');
			} else {
				throw new InternalErrorException('Invalid Action');
			}
		}
	}
