module.exports = function(slug) {
    if (slug && slug.charAt(0) === '_') {
        return true;
    }
    return false;
};
