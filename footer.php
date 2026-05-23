<?php
$footer_year = date('Y');
?>
<style>
    .tpo-footer {
        width: 100%;
        margin-top: 0;
        background: #111111;
        color: #ffffff;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .tpo-footer * {
        box-sizing: border-box;
    }

    .tpo-footer-inner {
        max-width: 1180px;
        margin: 0 auto;
        padding: 34px 28px 28px;
    }

    .tpo-footer-top {
        display: grid;
        grid-template-columns: 1.5fr repeat(4, 1fr);
        gap: 34px;
        align-items: start;
    }

    .tpo-footer-logo {
        color: #ffffff;
        font-size: 1.15rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        line-height: 1;
        margin-bottom: 8px;
        text-transform: uppercase;
    }

    .tpo-footer-tagline {
        color: #bdbdbd;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .tpo-footer h3 {
        margin: 0;
        color: #ffffff;
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.07em;
        line-height: 1.35;
        text-transform: uppercase;
    }

    .tpo-footer ul {
        list-style: none;
        margin: 8px 0 0;
        padding: 0;
    }

    .tpo-footer li,
    .tpo-footer a {
        color: #d8d8d8;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        line-height: 1.7;
        text-decoration: none;
        text-transform: uppercase;
    }

    .tpo-footer a:hover {
        color: #ffffff;
        text-decoration: underline;
    }

    .tpo-footer-rule {
        border: 0;
        border-top: 1px solid #5b5b5b;
        margin: 34px 0 22px;
    }

    .tpo-footer-social {
        display: flex;
        justify-content: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .tpo-footer-social a {
        width: 24px;
        height: 24px;
        border: 1px solid #ffffff;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 0.65rem;
        line-height: 1;
        text-decoration: none;
        text-transform: none;
    }

    .tpo-footer-copy {
        margin-top: 10px;
        color: #cfcfcf;
        font-size: 0.68rem;
        text-align: center;
    }

    @media (max-width: 900px) {
        .tpo-footer-top {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 560px) {
        .tpo-footer-top {
            grid-template-columns: 1fr;
        }
    }
</style>

<footer class="tpo-footer" id="tpoFooter">
    <div class="tpo-footer-inner">
        <div class="tpo-footer-top">
            <section>
                <div class="tpo-footer-logo">Task Performance Optimizer</div>
                <div class="tpo-footer-tagline">Academic contribution system</div>
            </section>

            <section>
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="login.php">Sign In</a></li>
                    <li><a href="register.php">Create Account</a></li>
                </ul>
            </section>

            <section>
                <h3>Services</h3>
                <ul>
                    <li>Task Tracking</li>
                    <li>Group Progress</li>
                    <li>Final Reports</li>
                </ul>
            </section>

            <section>
                <h3>Contact</h3>
                <ul>
                    <li>Administrator</li>
                    <li>Assigned Lecturer</li>
                    <li>support@task-performance.local</li>
                </ul>
            </section>

            <section>
                <h3>Policies</h3>
                <ul>
                    <li>Academic Use Only</li>
                    <li>Protect Student Data</li>
                    <li>Follow Integrity Rules</li>
                </ul>
            </section>
        </div>

        <hr class="tpo-footer-rule">

        <div class="tpo-footer-social" aria-label="Social links">
            <a href="index.php" aria-label="Website">w</a>
            <a href="login.php" aria-label="Account">u</a>
            <a href="register.php" aria-label="Register">+</a>
            <a href="mailto:support@task-performance.local" aria-label="Email">@</a>
            <a href="index.php" aria-label="More">...</a>
        </div>

        <div class="tpo-footer-copy">&copy; <?php echo $footer_year; ?> Copyright. All rights reserved.</div>
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
