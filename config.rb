# Require any additional compass plugins here.

# Set this to the root of your project when deployed:
project_type	= :stand_alone
http_path		= "/fluent"
css_dir			= "css"
sass_dir		= "scss"
images_dir		= "images"
javascripts_dir	= "javascript"
output_style	= :compressed
environment		= :production

# To enable relative paths to assets via compass helper functions. Uncomment:
relative_assets	= true

# disable comments in the output. We want admin comments
# to be verbose 
line_comments	= false

asset_cache_buster :none
