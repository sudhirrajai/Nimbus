<?php
/**
 * Nimbus DB SSO Wrapper
 */
@session_start();

if (isset($_GET['logout'])) {
    unset($_SESSION['adminer_username'], $_SESSION['adminer_password'], $_SESSION['adminer_server'], $_SESSION['adminer_db']);
    header('Location: /database');
    exit;
}

if (empty($_SESSION['adminer_username'])) {
    header('Location: /database?notice=unauthorized');
    exit;
}

if (isset($_SESSION['adminer_created']) && (time() - $_SESSION['adminer_created'] > 900)) {
    unset($_SESSION['adminer_username'], $_SESSION['adminer_password'], $_SESSION['adminer_server']);
    header('Location: /database?notice=session_expired');
    exit;
}

function adminer_object() {
    class NimbusDB extends Adminer {
        function name() { return 'Nimbus DB'; }
        function credentials() {
            return [$_SESSION['adminer_server'] ?? 'localhost', $_SESSION['adminer_username'], $_SESSION['adminer_password']];
        }
        function database() {
            return $_SESSION['adminer_db'] ?? '';
        }
        function databases($flush = true) {
            if (isset($_GET['server'])) {
                $return = get_databases($flush);
                $key = array_search('nimbus', $return);
                if ($key !== false) {
                    unset($return[$key]);
                }
                return array_values($return);
            }
            return [$_SESSION['adminer_db'] ?? ''];
        }
        function login($login, $password) {
            return true;
        }
    }
    return new NimbusDB;
}

ob_start(function($buffer) {
    // Replace text branding safely without breaking file paths
    $buffer = str_replace('Adminer', 'Nimbus DB', $buffer);
    $buffer = str_replace('<title>Nimbus DB', '<title>Nimbus Database', $buffer);
    
    // Remove donation message and version info completely
    $buffer = preg_replace('/<i[^>]*>\s*Thanks for using.*?donating.*?<\/i>/is', '', $buffer);
    $buffer = preg_replace('/Thanks for using.*?donating.*?<\/a>\./is', '', $buffer);
    $buffer = preg_replace('/<p class="version">.*?<\/p>/is', '', $buffer);
    
    // Inject custom enhancements before </body>
    $customScript = <<<'HTML'
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Convert breadcrumbs & links into Segmented Action Tabs
    const linksContainer = document.querySelector('p.links');
    if (linksContainer) {
        const params = new URLSearchParams(window.location.search);
        const db = params.get('db') || '';
        const username = params.get('username') || '';
        const server = params.get('server') || '';
        
        // Add "Back to Tables" button if inside table operations
        const isTableScope = params.has('table') || params.has('select') || params.has('schema') || params.has('create') || params.has('implode') || params.has('edit');
        if (isTableScope) {
            const backBtn = document.createElement('a');
            backBtn.className = 'back-to-tables-btn';
            backBtn.href = '?db=' + encodeURIComponent(db) + '&username=' + encodeURIComponent(username) + (server ? '&server=' + encodeURIComponent(server) : '');
            backBtn.innerHTML = '← Back to Tables';
            linksContainer.insertBefore(backBtn, linksContainer.firstChild);
        }
        
        // Add modern icons to navigation tabs
        const navLinks = linksContainer.querySelectorAll('a, b');
        navLinks.forEach(function(el) {
            if (el.classList.contains('back-to-tables-btn')) return;
            
            const text = el.textContent.trim().toLowerCase();
            if (text === 'show structure' || text.includes('structure')) {
                el.innerHTML = '📊 Structure';
            } else if (text === 'select data' || text.includes('select')) {
                el.innerHTML = '🔍 Browse';
            } else if (text === 'new item' || text.includes('new') || text.includes('insert')) {
                el.innerHTML = '➕ Insert Row';
            } else if (text === 'alter table' || text.includes('alter')) {
                el.innerHTML = '⚙️ Alter Table';
            } else if (text.includes('export')) {
                el.innerHTML = '📤 Export';
            } else if (text.includes('import')) {
                el.innerHTML = '📥 Import';
            } else if (text === 'database' || text.includes('schema')) {
                el.innerHTML = '🗄️ Database Schema';
            }
        });
    }

    // 2. Add dynamic Table Search Filter in Sidebar
    const tablesList = document.getElementById('tables');
    if (tablesList) {
        const searchDiv = document.createElement('div');
        searchDiv.className = 'table-search-box';
        searchDiv.innerHTML = '<input type="text" id="nimbus-table-filter" placeholder="Search tables..." autocomplete="off" />';
        
        // Insert above the tables list
        tablesList.parentNode.insertBefore(searchDiv, tablesList);
        
        const filterInput = document.getElementById('nimbus-table-filter');
        filterInput.addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase().trim();
            const items = tablesList.querySelectorAll('li');
            items.forEach(function(li) {
                const text = li.textContent.toLowerCase();
                if (text.includes(query)) {
                    li.style.display = '';
                } else {
                    li.style.display = 'none';
                }
            });
        });
    }
});
</script>
HTML;

    if (strpos($buffer, '</body>') !== false) {
        $buffer = str_replace('</body>', $customScript . '</body>', $buffer);
    }
    
    return $buffer;
});

include '/usr/share/adminer/adminer.php';
ob_end_flush();