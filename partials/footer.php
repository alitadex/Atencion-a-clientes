<?php
/**
 * partials/footer.php
 * ------------------------------------------------------------
 * PARA QUÉ SIRVE: parte INFERIOR común de todas las páginas.
 * Cierra los contenedores abiertos en header.php y carga el
 * JavaScript general (assets/app.js).
 * ------------------------------------------------------------
 */
?>
        </div><!-- /.page-wrap -->
    </main>
</div><!-- /.app-shell -->
<script src="<?= $basePath ?? "" ?>assets/app.js"></script>
<?php if (!empty($mostrarLoaderPerfil)): ?>
<script src="<?= $basePath ?? "" ?>assets/loader.js"></script>
<?php endif; ?>
</body>
</html>
