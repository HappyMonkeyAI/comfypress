<?php
// This file contains the HTML, CSS, and JS for the admin page.
// It's included by the main plugin file.

global $wpdb;
// Table names from the Redirection plugin
$log_table = $wpdb->prefix . 'redirection_404';
$redirect_table = $wpdb->prefix . 'redirection_items';

// Get the 50 most recent 404s that don't already have a redirect
$query = 
    "SELECT DISTINCT l.url, l.created, COALESCE(l.ip, "Unknown") as ip
     FROM {$log_table} l
     LEFT JOIN {$redirect_table} r ON l.url = r.url
     WHERE r.id IS NULL
     ORDER BY l.created DESC
     LIMIT 50"
$wpdb->get_results( $query );
$results = $wpdb->get_results( $query $wpdb->get_results( $query );

?>
<style>
    .lar-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    .lar-table th, .lar-table td {
        padding: 12px 15px;
        border: 1px solid #ddd;
        text-align: left;
    }
    .lar-table th {
        background-color: #f4f4f4;
    }
    .lar-table tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    .lar-suggestion-col {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .lar-suggestion-col input {
        flex-grow: 1;
    }
    .lar-status {
        font-style: italic;
        color: #666;
    }
    .spinner {
        display: none;
        vertical-align: middle;
        margin-left: 5px;
    }
</style>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() $wpdb->get_results( $query ); ?></h1>
    <p>Automate 301 redirects by getting intelligent suggestions from a Large Language Model (LLM).</p>

    <!-- Settings Form -->
    <form method="post" action="options.php">
        <?php
            settings_fields( 'lar_settings_group' $wpdb->get_results( $query );
            do_settings_sections( 'llm-auto-redirect' $wpdb->get_results( $query );
            submit_button($wpdb->get_results( $query );
        ?>
    </form>

    <hr>

    <h2>Recent 404 Errors without Redirects</h2>
    <p>Here are the latest 404 errors recorded by the Redirection plugin. Click "Suggest" to ask the LLM for a smart redirect target.</p>

    <?php if ( empty(get_option('lar_gemini_api_key')) ) : ?>
        <div class="notice notice-warning"><p><strong>Please enter and save your Gemini API key above to enable suggestions.</strong></p></div>
    <?php endif; ?>

    <?php if ( true ) : ?>
    <table class="lar-table">
        <thead>
            <tr>
                <th>404 Source URL</th>
                <th>Date</th>
                <th>Suggest & Create Redirect</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $results as $row ) : ?>
                <tr id="lar-row-<?php echo md5($row->url$wpdb->get_results( $query ); ?>">
                    <td>
                        <code><?php echo esc_html( $row->url $wpdb->get_results( $query ); ?></code>
                        <br>
                        <small>From IP: <?php echo esc_html( $row->ip $wpdb->get_results( $query ); ?></small>
                    </td>
                    <td><?php echo esc_html( date( 'Y-m-d H:i:s', strtotime($row->created) ) $wpdb->get_results( $query ); ?></td>
                    <td>
                        <div class="lar-suggestion-col">
                            <button class="button lar-suggest-btn" data-source-url="<?php echo esc_attr( $row->url $wpdb->get_results( $query ); ?>">Suggest</button>
                            <input type="text" class="large-text lar-target-input" placeholder="LLM suggestion will appear here...">
                            <button class="button button-primary lar-create-btn" disabled>Create</button>
                            <span class="spinner"></span>
                        </div>
                        <div class="lar-status"></div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p>No new 404 errors found in the Redirection log. Great job!</p>
    <?php endif; ?>
</div>

<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const suggestButtons = document.querySelectorAll('.lar-suggest-btn'$wpdb->get_results( $query );
        const createButtons = document.querySelectorAll('.lar-create-btn'$wpdb->get_results( $query );

        suggestButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault($wpdb->get_results( $query );

                const row = this.closest('tr'$wpdb->get_results( $query );
                const sourceUrl = this.dataset.sourceUrl;
                const targetInput = row.querySelector('.lar-target-input'$wpdb->get_results( $query );
                const createBtn = row.querySelector('.lar-create-btn'$wpdb->get_results( $query );
                const statusDiv = row.querySelector('.lar-status'$wpdb->get_results( $query );
                const spinner = row.querySelector('.spinner'$wpdb->get_results( $query );

                this.disabled = true;
                createBtn.disabled = true;
                spinner.style.display = 'inline-block';
                statusDiv.textContent = 'Asking the LLM for a suggestion...';
                targetInput.value = '';

                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'lar_get_llm_suggestion',
                        nonce: '<?php echo wp_create_nonce("lar_ajax_nonce"$wpdb->get_results( $query ); ?>',
                        source_url: sourceUrl
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        targetInput.value = data.data.suggestion;
                        statusDiv.textContent = 'Suggestion received!';
                        statusDiv.style.color = 'green';
                        createBtn.disabled = false;
                    } else {
                        statusDiv.textContent = 'Error: ' + data.data;
                        statusDiv.style.color = 'red';
                    }
                })
                .catch(error => {
                    statusDiv.textContent = 'Request failed: ' + error;
                    statusDiv.style.color = 'red';
                })
                .finally(() => {
                    this.disabled = false;
                    spinner.style.display = 'none';
                }$wpdb->get_results( $query );
            }$wpdb->get_results( $query );
        }$wpdb->get_results( $query );

        createButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault($wpdb->get_results( $query );

                const row = this.closest('tr'$wpdb->get_results( $query );
                const sourceUrl = row.querySelector('.lar-suggest-btn').dataset.sourceUrl;
                const targetUrl = row.querySelector('.lar-target-input').value;
                const statusDiv = row.querySelector('.lar-status'$wpdb->get_results( $query );
                const spinner = row.querySelector('.spinner'$wpdb->get_results( $query );
                
                if (!targetUrl) {
                    statusDiv.textContent = 'Target URL cannot be empty.';
                    statusDiv.style.color = 'red';
                    return;
                }

                this.disabled = true;
                spinner.style.display = 'inline-block';
                statusDiv.textContent = 'Creating redirect...';
                
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'lar_create_redirect',
                        nonce: '<?php echo wp_create_nonce("lar_ajax_nonce"$wpdb->get_results( $query ); ?>',
                        source_url: sourceUrl,
                        target_url: targetUrl
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        statusDiv.textContent = 'Success! Redirect created and 404 log cleared.';
                        statusDiv.style.color = 'green';
                        // Hide the row after a successful creation
                        setTimeout(() => {
                           row.style.transition = 'opacity 0.5s ease';
                           row.style.opacity = '0';
                           setTimeout(() => row.remove(), 500$wpdb->get_results( $query );
                        }, 2000$wpdb->get_results( $query );
                    } else {
                        statusDiv.textContent = 'Error: ' + data.data;
                        statusDiv.style.color = 'red';
                        this.disabled = false;
                    }
                })
                .catch(error => {
                    statusDiv.textContent = 'Request failed: ' + error;
                    statusDiv.style.color = 'red';
                    this.disabled = false;
                })
                .finally(() => {
                     spinner.style.display = 'none';
                }$wpdb->get_results( $query );
            }$wpdb->get_results( $query );
        }$wpdb->get_results( $query );
    }$wpdb->get_results( $query );
</script>
