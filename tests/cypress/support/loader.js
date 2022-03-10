class Loader {
    static po = {}
    static data = {}

    static loadPO(classNames) {

        if(! Array.isArray(classNames)) {
            classNames = [classNames]
        }

        classNames.forEach((className) => {
            const Object = require("../pageObjects/" + className)
            this.po = this.setObject(className, this.po, new Object())
        })
    }

    static setObject(path, object, classObject) {
        if(path.indexOf('/') === -1) {
            object[path] = classObject
            return object
        }
        
        const slices = path.split('/'),
            first = slices[0],
            newPath = slices.slice(1).join('/')

        if(!object[first]) {
            object[first] = this.setObject(newPath, {}, classObject)
        } else {
            object[first] = this.setObject(newPath, object[first], classObject)
        }

        return object
    }

    static loadData(files) {
        var loadFile = (file) => {
            return require('../fixtures/data/' + Cypress.env('lang') + '/' + file + '.json')
        }

        var loadedData = []
        if(Array.isArray(files)) {
            files.forEach(function(file, i) {
                loadedData[file] = loadFile(file)
            })
        } else {
            loadedData[files] = loadFile(files)
        }

        this.data = loadedData
    }
}

export default Loader