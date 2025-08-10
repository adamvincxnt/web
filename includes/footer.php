    </main>
    
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-utensils me-2"></i>ร้านอาหารออนไลน์</h5>
                    <p>สั่งอาหารง่าย ๆ ผ่านระบบออนไลน์</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <h6>ติดต่อเรา</h6>
                    <p>
                        <i class="fas fa-phone me-2"></i><?php echo PROMPTPAY_PHONE; ?><br>
                        <i class="fas fa-envelope me-2"></i>info@restaurant.com
                    </p>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> ร้านอาหารออนไลน์. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    
    <?php if (isset($extra_js)): ?>
        <?php foreach ($extra_js as $js_file): ?>
            <script src="<?php echo SITE_URL; ?>/assets/js/<?php echo $js_file; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>