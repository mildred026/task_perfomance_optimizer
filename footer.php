<?php
$footer_year = date('Y');
?>
<style>
    .tpo-footer {
        width: 100%;
        margin-top: 28px;
        background: #101828;
        color: #d7e0ec;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        border-top: 3px solid #667eea;
    }

    .tpo-footer * {
        box-sizing: border-box;
    }

    .tpo-footer-inner {
        max-width: 1120px;
        margin: 0 auto;
        padding: 20px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        flex-wrap: wrap;
    }

    .tpo-footer-title {
        color: #ffffff;
        font-weight: 700;
        font-size: 1rem;
        margin-bottom: 4px;
    }

    .tpo-footer p {
        margin: 0;
        color: #c7d2df;
        font-size: 0.86rem;
        line-height: 1.55;
    }

    .tpo-footer-links {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
        font-size: 0.85rem;
    }

    .tpo-footer-links span {
        color: #e5edf7;
        white-space: nowrap;
    }

    @media (max-width: 700px) {
        .tpo-footer-inner {
            align-items: flex-start;
            flex-direction: column;
        }

        .tpo-footer-links {
            flex-direction: column;
            gap: 6px;
        }
    }
</style>

<footer class="tpo-footer" id="tpoFooter">
    <div class="tpo-footer-inner">
        <div>
            <div class="tpo-footer-title">Task Performance Optimizer</div>
            <p>&copy; <?php echo $footer_year; ?> Academic group contribution tracking system.</p>
        </div>

        <div class="tpo-footer-links" aria-label="Project contact, policies and regulations">
            <span>Contact: support@task-performance.local</span>
            <span>Policy: academic use only</span>
            <span>Regulation: protect student data and submissions</span>
        </div>
    </div>
</footer>

<script>
    (function () {
        var footer = document.getElementById('tpoFooter');
        if (!footer) {
            return;
        }

        var target = document.querySelector('.main-content, .app-container .main, .app-container .content, main');
        if (target && !target.contains(footer)) {
            target.appendChild(footer);
            return;
        }

        if (footer.parentElement === document.body) {
            document.body.style.flexDirection = 'column';
            document.body.style.alignItems = 'stretch';
        }
    })();
</script>
