<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function zeecreatives_multisites_admin_page()
{
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form id="create-site-form">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="site_url">Subdomain</label></th>
                    <td>
                        <input name="site_url" type="text" id="site_url" class="regular-text" required>
                        <p class="description">Enter the subdomain for the new site (e.g., 'newsite' for
                            newsite.<?php echo esc_html(DOMAIN_CURRENT_SITE); ?>)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="site_name">Site Name</label></th>
                    <td>
                        <input name="site_name" type="text" id="site_name" class="regular-text" required>
                        <p class="description">Enter the main domain for the new site (e.g.<?php echo esc_html(DOMAIN_CURRENT_SITE); ?>)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="admin_email">Site Admin Email</label></th>
                    <td>
                        <input name="admin_email" type="email" id="admin_email" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="admin_password">Site Admin Password</label></th>
                    <td>
                        <input name="admin_password" type="password" id="admin_password" class="regular-text" required>
                        <p class="description">Password must contains 8 or more characters</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="current_user_password">Your Password </label></th>
                    <td>
                        <input name="current_user_password" type="password" id="current_user_password" class="regular-text"
                            required>
                        <p class="description">Enter Your Password for Authorization</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Create New Site">
            </p>
        </form>
        <div id="response-message"></div>
    </div>

    <?php
}
