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
            if (isset($_POST['auth']['username'])) {
                return [$_POST['auth']['server'] ?? 'localhost', $_POST['auth']['username'], $_POST['auth']['password'] ?? ''];
            }
            if (isset($_GET['username'])) {
                return parent::credentials();
            }
            return [$_SESSION['adminer_server'] ?? 'localhost', $_SESSION['adminer_username'], $_SESSION['adminer_password']];
        }
        
        function database() {
            if (isset($_POST['auth']['db'])) {
                return $_POST['auth']['db'];
            }
            if (isset($_GET['username']) || isset($_GET['db'])) {
                return parent::database();
            }
            return $_SESSION['adminer_db'] ?? '';
        }
        
        function login($login, $password) {
            return true;
        }
    }
    return new NimbusDB;
}

ob_start(function($buffer) {
    // Replace text branding safely
    $buffer = preg_replace('/<h2[^>]*>Login<\/h2>/i', '<h2>Nimbus DB Login</h2>', $buffer);
    $buffer = str_replace('<title>Login - Adminer</title>', '<title>Login - Nimbus DB</title>', $buffer);
    $buffer = str_replace('<title>Adminer</title>', '<title>Nimbus DB</title>', $buffer);
    $buffer = str_replace('Adminer', 'System', $buffer);
    $buffer = str_replace('<title>System', '<title>Nimbus DB', $buffer);
    
    // Remove donation message
    $buffer = preg_replace('/<i[^>]*>\s*Thanks for using.*?donating.*?<\/i>/is', '', $buffer);
    $buffer = preg_replace('/Thanks for using.*?donating.*?<\/a>\./is', '', $buffer);
    
    // Inject JS for sidebar simplification and UI enhancements
    $js = <<<JS
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Simplify sidebar: table name click goes to data directly, hide 'select' link
    document.querySelectorAll("#tables li").forEach(function(li) {
        var links = li.querySelectorAll("a");
        if (links.length >= 2) {
            links[0].href = links[1].href;
            links[1].style.display = "none";
        }
    });

    // Add "Back to Tables" button in table views
    var linksContainer = document.querySelector('p.links');
    if (linksContainer) {
        var params = new URLSearchParams(window.location.search);
        var db = params.get('db') || '';
        var username = params.get('username') || '';
        var server = params.get('server') || '';
        
        var isTableScope = params.has('table') || params.has('select') || params.has('create') || params.has('edit');
        if (isTableScope && db) {
            var backBtn = document.createElement('a');
            backBtn.className = 'back-to-tables-btn';
            backBtn.href = '?username=' + encodeURIComponent(username) + '&db=' + encodeURIComponent(db) + (server ? '&server=' + encodeURIComponent(server) : '');
            backBtn.innerHTML = '\u2190 Back to Tables';
            linksContainer.insertBefore(backBtn, linksContainer.firstChild);
        }
        
        // Add modern icons to navigation tabs
        var navLinks = linksContainer.querySelectorAll('a, b');
        navLinks.forEach(function(el) {
            if (el.classList.contains('back-to-tables-btn')) return;
            var text = el.textContent.trim().toLowerCase();
            if (text === 'show structure' || text === 'structure') {
                el.innerHTML = '\uD83D\uDCCA Structure';
            } else if (text === 'select data') {
                el.innerHTML = '\uD83D\uDD0D Browse';
            } else if (text === 'new item') {
                el.innerHTML = '\u2795 Insert Row';
            } else if (text === 'alter table') {
                el.innerHTML = '\u2699\uFE0F Alter Table';
            } else if (text === 'export') {
                el.innerHTML = '\uD83D\uDCE4 Export';
            } else if (text === 'import') {
                el.innerHTML = '\uD83D\uDCE5 Import';
            }
        });
    }

    // Add dynamic Table Search Filter in Sidebar
    var tablesList = document.getElementById('tables');
    if (tablesList) {
        var searchDiv = document.createElement('div');
        searchDiv.className = 'table-search-box';
        searchDiv.innerHTML = '<input type="text" id="nimbus-table-filter" placeholder="Search tables..." autocomplete="off" />';
        tablesList.parentNode.insertBefore(searchDiv, tablesList);
        
        document.getElementById('nimbus-table-filter').addEventListener('input', function(e) {
            var query = e.target.value.toLowerCase().trim();
            tablesList.querySelectorAll('li').forEach(function(li) {
                li.style.display = li.textContent.toLowerCase().includes(query) ? '' : 'none';
            });
        });
    }
});
</script>
</head>
JS;
    $buffer = str_replace('</head>', $js, $buffer);
    
    return $buffer;
});

include '/usr/share/adminer/adminer.php';
ob_end_flush();