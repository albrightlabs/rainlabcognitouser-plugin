<?php namespace Albrightlabs\RainlabUserCognito;

use App;
use Event;
use Flash;
use RainLab\User\Models\User;
use ValidationException;
use Illuminate\Http\Request;
use System\Classes\PluginBase;
use BlackBits\LaravelCognitoAuth\CognitoClient;
use AlbrightLabs\RainlabUserCognito\Providers\CognitoAuthServiceProvider;

/**
 * Plugin Information File
 *
 * @link https://docs.octobercms.com/3.x/extend/system/plugins.html
 */
class Plugin extends PluginBase
{
    /**
     * pluginDetails about this plugin.
     */
    public function pluginDetails()
    {
        return [
            'name' => 'RainLab User Cognito',
            'description' => 'Extends RainLab User for AWS Cognito support.',
            'author' => 'Albright Labs LLC',
            'icon' => 'icon-leaf'
        ];
    }

    /**
     * register method, called when the plugin is first registered.
     */
    public function register()
    {
        /**
         * Register auth provider for AWS Cognito
         */
        App::register(CognitoAuthServiceProvider::class);
    }

    /**
     * boot method, called right before the request route.
     */
    public function boot()
    {
        /**
         * Listens for when a user is saved and registers them in Cognito after save
         */
        User::extend(function($model) {
            $model->bindEvent('model.beforeSave', function () use ($model) {

                // only create cognito user if option is checked
                if (!$model->is_cognito_user) {
                    return true;
                }

                // only create cognito user if non-existing
                if ($model->is_cognito_user_existing == 1) {
                    return true;
                }

                // generate the user's name from data
                $fullName = '';
                if (null != $model->name) {
                    $fullName .= $model->name;
                }
                if (null != $model->surname) {
                    if ($fullName != '') { $fullName .= ' '; }
                    $fullName .= $model->surname;
                }

                // throw error if no name
                if (null == $fullName || $fullName == '') {
                    throw new ValidationException(['name' => 'Provide a first or last name.']);
                }

                // throw error if no email
                if (null == $model->email) {
                    throw new ValidationException(['email' => 'Provide an email address.']);
                }

                // throw error if no password
                if (null == $model->getOriginalHashValue('password')) {
                    throw new ValidationException(['password' => 'Provide a new password.']);
                }

                // create request data
                $request = new Request();
                $request->replace([
                    'name'     => $fullName,
                    'email'    => $model->email,
                    'password' => $model->getOriginalHashValue('password'),
                ]);

                // attempt register user in cognito
                if (app()->make(CognitoClient::class)->register($request->email, $request->password, [])) {
                    // registered user in cognito, note this locally
                    $model->is_cognito_user_existing = 1;
                }
                else {
                    // could not register user in cognito, stop creation of local user
                    Flash::error('User could not be created in AWS Cognito.');
                    return false;
                }
            });
        });

        /**
         * Add a checkbox for allowing front-end login via Cognito
         */
        Event::listen('backend.form.extendFields', function ($widget) {
            if (!$widget->getController() instanceof \Rainlab\User\Controllers\Users) { return; }
            if (!$widget->model instanceof \Rainlab\User\Models\User) { return; }
            $widget->addFields([
                'is_cognito_user' => [
                    'label' => 'Allow Front-end Login',
                    'type'  => 'checkbox',
                ]
            ]);
        });
    }

    /**
     * registerComponents used by the frontend.
     */
    public function registerComponents()
    {
        return [
            'Albrightlabs\RainlabUserCognito\Components\Login'                => 'Login',
            'Albrightlabs\RainlabUserCognito\Components\ResetPassword'        => 'ResetPassword',
            'Albrightlabs\RainlabUserCognito\Components\RequestResetPassword' => 'RequestResetPassword',
        ];
    }
}
