<?php
/**
 * Person fields for Add/Edit Contact — layout matches supplier form reference.
 */
function lcRenderPersonFields($formId = 'profile')
{
    ?>
    <div class="lc-person-fields lc-person-fields--supplier">
        <div class="lc-profile-photo-row">
            <div class="lc-profile-photo-preview" id="lcProfilePhotoPreview">
                <span class="lc-profile-photo-placeholder"><i class="fas fa-user"></i></span>
                <img src="" alt="Profile" class="lc-profile-photo-img d-none">
            </div>
            <div class="lc-profile-photo-meta">
                <label class="mb-1">Profile Image</label>
                <div class="lc-profile-photo-actions">
                    <label class="btn btn-sm btn-outline-secondary mb-0 lc-profile-photo-btn">
                        <i class="fas fa-camera mr-1"></i> Upload photo
                        <input type="file" name="profile_photo" id="lcProfilePhotoInput" accept="image/*" hidden>
                    </label>
                    <button type="button" class="btn btn-sm btn-link text-danger px-1 d-none" id="lcProfilePhotoClear">Remove</button>
                </div>
                <input type="hidden" name="clear_profile_photo" id="lcClearProfilePhoto" value="">
                <p class="small text-muted mb-0 mt-1">JPG, PNG, WEBP or GIF — max 5MB</p>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label>Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required autocomplete="name">
            </div>
            <div class="form-group col-md-6">
                <label>Website</label>
                <input type="text" name="website" id="lcPersonWebsite" class="form-control" placeholder="Website" autocomplete="url">
            </div>
        </div>

        <div class="lc-contact-rows" id="lcContactRows">
            <div class="form-row lc-contact-row align-items-end">
                <div class="form-group col-md-4">
                    <label>Contact Name</label>
                    <input type="text" class="form-control js-lc-c-name" placeholder="Contact name" autocomplete="off">
                </div>
                <div class="form-group col-md-4">
                    <label>Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control js-lc-c-email" placeholder="Email" autocomplete="off">
                </div>
                <div class="form-group col-md-3">
                    <label>Mobile No</label>
                    <input type="text" class="form-control js-lc-c-mobile" placeholder="Mobile number" autocomplete="tel">
                </div>
                <div class="form-group col-md-1 lc-contact-action-wrap">
                    <label class="lc-contact-action-label">&nbsp;</label>
                    <button type="button" class="btn lc-btn-contact-action js-lc-contact-add" title="Add contact">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
        </div>
        <input type="hidden" name="email" id="lcPersonEmail" value="">
        <input type="hidden" name="mobile" id="lcPersonMobile" value="">

        <div class="form-row">
            <div class="form-group col-md-6 lc-city-wrap">
                <label>City</label>
                <input type="text" name="city" id="lcPersonCity" class="form-control" placeholder="Search City" autocomplete="off">
                <div class="lc-city-dropdown" id="lcCitySearchDropdown"></div>
            </div>
            <div class="form-group col-md-6">
                <label>Physical Address</label>
                <input type="text" name="address" class="form-control" placeholder="Physical address" autocomplete="street-address">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-4">
                <label>Title</label>
                <select name="title" class="form-control">
                    <option value="">—</option>
                    <option>Mr</option>
                    <option>Mrs</option>
                    <option>Ms</option>
                    <option>Master</option>
                    <option>Miss</option>
                </select>
            </div>
            <div class="form-group col-md-4">
                <label>Date of Birth</label>
                <input type="date" name="date_of_birth" class="form-control">
            </div>
            <div class="form-group col-md-4">
                <label>Gender</label>
                <select name="gender" class="form-control">
                    <option value="">—</option>
                    <option>Male</option>
                    <option>Female</option>
                    <option>Other</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-4">
                <label>PAN Photo</label>
                <input type="file" name="pan_photo" class="form-control-file" accept="image/*,.pdf">
                <div class="lc-preview lc-preview-pan small mt-1"></div>
            </div>
            <div class="form-group col-md-4">
                <label>Aadhar Photo</label>
                <input type="file" name="aadhar_photo" class="form-control-file" accept="image/*,.pdf">
                <div class="lc-preview lc-preview-aadhar small mt-1"></div>
            </div>
            <div class="form-group col-md-4 mb-0">
                <label>Other Document</label>
                <input type="file" name="other_document" class="form-control-file" accept="image/*,.pdf">
                <div class="lc-preview lc-preview-other small mt-1"></div>
            </div>
        </div>
    </div>
    <?php
}
