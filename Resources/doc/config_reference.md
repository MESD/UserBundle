##MesdUserBundle Configuration Reference

Below is a list of all configuration options and their defaults.


```yaml
# app/config/config.yml

mesd_user:
    user_class:         ~ # Required - Your applications user entity class
    role_class:         ~ # Required - Your applications role entity class
    group_class:        ~ # Your applications group entity class

    login:
        template:       MesdUserBundle:security:login.html.twig

    registration:
        enabled:        false
        template:       MesdUserBundle:security:registration.html.twig
        text:           'Create Account'

    resetting:
        enabled:        false
        template:       MesdUserBundle:security:resetting.html.twig
        text:           'Reset Password'

```