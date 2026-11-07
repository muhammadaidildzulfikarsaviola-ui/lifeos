        </div>

        <footer class="app-footer">
            <span>LIFEOS</span>
            <span>Your life, in one place.</span>
        </footer>
    </main>

</div>

<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<?php if ($moduleJsUrl !== null): ?>
    <script src="<?= e($moduleJsUrl) ?>"></script>
<?php endif; ?>
</body>
</html>
