<?php
namespace modules\users\controllers\frontend;

use Yii;
use yii\web\Controller;
use modules\users\models\User;
use modules\users\models\SignupForm;
use modules\users\models\LoginForm;
use modules\users\models\EmailConfirmForm;
use modules\users\models\ResetPasswordForm;
use modules\users\models\PasswordResetRequestForm;
use yii\base\InvalidParamException;
use yii\web\BadRequestHttpException;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\bootstrap\ActiveForm;
use yii\web\Response;
use modules\users\Module;

/**
 * Class DefaultController
 * @package modules\users\controllers\frontend
 */
class DefaultController extends Controller
{
    /**
     * @return array
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionIndex()
    {
        return $this->render('index', [
            'model' => $this->findModel(),
        ]);
    }

    /**
     * @return array|string|Response
     * @throws NotFoundHttpException
     */
    public function actionUpdate()
    {
        $model = $this->findModel();
        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * @return array|string|Response
     * @throws NotFoundHttpException
     */
    public function actionUpdatePassword()
    {
        $model = $this->findModel();
        $model->scenario = $model::SCENARIO_PASSWORD_UPDATE;

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }

        if ($model->load(Yii::$app->request->post())) {
            if ($model->save())
                Yii::$app->session->setFlash('success', Module::t('module', 'Password changed successfully.'));
        }
        return $this->redirect(['update', 'tab' => 'password']);
    }

    /**
     * @return string|Response
     * @throws NotFoundHttpException
     */
    public function actionUpdateProfile()
    {
        $model = $this->findModel();
        $model->scenario = $model::SCENARIO_PROFILE_UPDATE;
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', Module::t('module', 'Profile successfully changed.'));
        }
        return $this->redirect(['update', 'tab' => 'profile']);
    }

    /**
     * Deletes an existing User model.
     * This delete set status blocked, is successful, logout and the browser will be redirected to the 'home' page.
     * @return mixed
     */
    public function actionDelete()
    {
        $model = $this->findModel();
        $model->scenario = $model::SCENARIO_PROFILE_DELETE;
        $model->status = $model::STATUS_DELETED;
        if ($model->save())
            Yii::$app->user->logout();
        return $this->goHome();
    }

    /**
     * Logs in a user.
     *
     * @return mixed
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        } else {
            return $this->render('login', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Logs out the current user.
     *
     * @return mixed
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Signs user up.
     *
     * @return mixed
     */
    public function actionSignup()
    {
        $model = new SignupForm();
        if ($model->load(Yii::$app->request->post())) {
            if ($user = $model->signup()) {
                Yii::$app->getSession()->setFlash('success', Module::t('module', 'It remains to activate the account.'));
                return $this->goHome();
            }
        }
        return $this->render('signup', [
            'model' => $model,
        ]);
    }

    /**
     * @param $token
     * @return \yii\web\Response
     * @throws BadRequestHttpException
     */
    public function actionEmailConfirm($token)
    {
        try {
            $model = new EmailConfirmForm($token);
        } catch (InvalidParamException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($model->confirmEmail()) {
            Yii::$app->getSession()->setFlash('success', Module::t('module', 'Thank you for registering!'));
        } else {
            Yii::$app->getSession()->setFlash('error', Module::t('module', 'Error sending message!'));
        }

        return $this->goHome();
    }

    /**
     * Requests password reset.
     *
     * @return mixed
     */
    public function actionRequestPasswordReset()
    {
        $model = new PasswordResetRequestForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail()) {
                Yii::$app->session->setFlash('success', Module::t('module', 'Check your email for further instructions.'));

                return $this->goHome();
            } else {
                Yii::$app->session->setFlash('error', Module::t('module', 'Sorry, we are unable to reset password.'));
            }
        }

        return $this->render('requestPasswordResetToken', [
            'model' => $model,
        ]);
    }

    /**
     * Resets password.
     *
     * @param string $token
     * @return mixed
     * @throws BadRequestHttpException
     */
    public function actionResetPassword($token)
    {
        try {
            $model = new ResetPasswordForm($token);
        } catch (InvalidParamException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($model->load(Yii::$app->request->post()) && $model->validate() && $model->resetPassword()) {
            Yii::$app->session->setFlash('success', Module::t('module', 'Password changed successfully.'));
            return $this->goHome();
        }

        return $this->render('resetPassword', [
            'model' => $model,
        ]);
    }

    /**
     * Finds the User model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @return User the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel()
    {
        $id = Yii::$app->user->identity->getId();
        if (($model = User::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException(Module::t('module', 'The requested page does not exist.'));
        }
    }
}
